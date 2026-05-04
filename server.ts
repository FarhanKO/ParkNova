import express from "express";
import { createServer as createViteServer } from "vite";
import path from "path";
// @ts-ignore
import cors from "cors";
import dotenv from "dotenv";
import mysql from "mysql2/promise";
import bcrypt from "bcrypt";
import { fileURLToPath } from "url";

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

async function startServer() {
  const app = express();
  const PORT = 3000;

  app.use(cors());
  app.use(express.json());

  console.log("\n=============================================");
  console.log("[SERVER] Booting up ParkNova AI Backend...");
  console.log("[SERVER] Schema Updated: Using 'vehicleNumber'");
  console.log("=============================================\n");
  
  // Create a MySQL connection pool
  const pool = mysql.createPool({
    host: 'localhost',
    user: 'root',
    password: '', // Default XAMPP password is empty
    database: 'parknova_db',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
  });

  // Test the connection
  pool.getConnection()
    .then(async connection => {
      console.log('[DB] Connected to MySQL database (parknova_db) successfully!');
      
      try {
        // 0. Ensure missing columns exist in User table for Loyalty and VIP
        try {
          await connection.query("ALTER TABLE User ADD loyaltyPoints INT DEFAULT 0, ADD violationPoints INT DEFAULT 0, ADD isVIP BOOLEAN DEFAULT FALSE");
          console.log("[DB] Added loyalty and VIP columns to User table");
        } catch (colErr) { /* Columns likely already exist */ }

        // 1. Seed Parking Areas if missing
        const [areaRows]: any = await connection.query("SELECT COUNT(*) as count FROM Parking_Area");
        if (areaRows[0].count === 0) {
          console.log('[DB] Seeding Parking Areas...');
          await connection.query("INSERT IGNORE INTO Parking_Area (zone_id, zone_name, capacity) VALUES (1, 'Zone A', 3), (2, 'Zone B', 3), (3, 'Zone C', 3)");
        }

        // 2. Seed Slots if missing
        const [slotRows]: any = await connection.query("SELECT COUNT(*) as count FROM Slot");
        if (slotRows[0].count === 0) {
          console.log('[DB] Seeding Slots...');
          for (let i = 1; i <= 9; i++) {
            const sType = i % 3 === 0 ? 'VIP' : 'Standard';
            const zoneId = Math.ceil(i / 3); // 1, 2, 3
            await connection.query(
              "INSERT IGNORE INTO Slot (slot_id, zone_id, floor_level, occupancy, slot_type) VALUES (?, ?, 1, 0, ?)",
              [i, zoneId, sType]
            );
          }
        }
        
        // 3. Seed Default Admin if missing
        const [adminRows]: any = await connection.query("SELECT COUNT(*) as count FROM User WHERE username = 'admin'");
        if (adminRows[0].count === 0) {
          const adminPass = await bcrypt.hash('admin123', 10);
          await connection.query("INSERT IGNORE INTO User (password, username, user_type, full_name) VALUES (?, 'admin', 'Admin', 'System Admin')", [adminPass]);
        }
      } catch (seedErr) {
        console.error('[DB] Error during seeding:', seedErr);
      }

      connection.release();
    })
    .catch(err => {
      console.error('[DB] FATAL: Could not connect to MySQL database.', err);
    });

  // --- API ROUTES ---

  app.get("/api/health", (req, res) => res.json({ status: "ok" }));

  app.get("/api/settings/pricing", async (req, res) => {
    // This is a mock since settings table doesn't exist in the MySQL schema.
    // We can add it later if needed.
    res.json({ baseRate: 5 });
  });

  app.get("/api/slots", async (req, res) => {
    try {
      const [rows] = await pool.query(
        "SELECT CAST(slot_id AS CHAR) as id, slot_type as type, CASE WHEN occupancy = 1 THEN 'occupied' ELSE 'available' END as status FROM Slot"
      );
      res.json(rows);
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  app.post("/api/slots/allocate", async (req, res) => {
    const { slotId, vehicleNumber, userId, vehicleType } = req.body;
    const vType =
      vehicleType === "Emergency" ? "VIP" : vehicleType || "Standard";
      
    const parsedUserId = parseInt(userId);
    const validUserId = isNaN(parsedUserId) ? null : parsedUserId;
    const now = new Date();
    let connection;
    try {
      connection = await pool.getConnection();
      await connection.beginTransaction();

      await connection.query("UPDATE Slot SET occupancy = 1 WHERE slot_id = ?", [slotId]);
      
      await connection.query(
        "INSERT INTO Vehicles (license_plate, user_id, vehicle_type) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE vehicle_type = VALUES(vehicle_type)",
        [vehicleNumber, validUserId, vType]
      );

      const [result]: any = await connection.query(
        "INSERT INTO Session (license_plate, entry_time) VALUES (?, ?)",
        [vehicleNumber, now]
      );
      const sessionId = result.insertId;

      await connection.query(
        "INSERT INTO Session_Slot (session_id, slot_id) VALUES (?, ?)",
        [sessionId, slotId]
      );

      await connection.commit();
      res.json({ success: true, sessionId: sessionId.toString() });
    } catch (err: any) {
      if (connection) await connection.rollback();
      res.status(500).json({ success: false, error: err.message });
    } finally {
      if (connection) connection.release();
    }
  });

  app.get("/api/sessions/active", async (req, res) => {
    try {
      const [rows] = await pool.query(
        "SELECT CAST(s.session_id AS CHAR) as id, CAST(v.user_id AS CHAR) as userId, s.license_plate as vehicleNumber, CAST(ss.slot_id AS CHAR) as slotId, s.entry_time as entryTime, v.vehicle_type as vehicleType, CASE WHEN p.slip_id IS NOT NULL THEN 1 ELSE 0 END as isPrepaid, p.final_fee as fee, 'active' as status FROM Session s JOIN Session_Slot ss ON s.session_id = ss.session_id JOIN Vehicles v ON s.license_plate = v.license_plate LEFT JOIN Payment p ON s.session_id = p.session_id WHERE s.exit_time IS NULL"
      );
      res.json(rows);
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  app.post("/api/reserve", async (req, res) => {
    const {
      slotId,
      vehicleNumber,
      userId,
      vehicleType,
      entryTime,
      duration,
      fee,
      paymentMethod,
      trxId,
    } = req.body;
    const vType = vehicleType || "Standard";
    
    const parsedUserId = parseInt(userId);
    const validUserId = isNaN(parsedUserId) ? null : parsedUserId;
    const now = new Date();
    let connection;
    try {
      connection = await pool.getConnection();
      await connection.beginTransaction();

      await connection.query("UPDATE Slot SET occupancy = 1 WHERE slot_id = ?", [slotId]);
      
      await connection.query(
        "INSERT INTO Vehicles (license_plate, user_id, vehicle_type) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE vehicle_type = VALUES(vehicle_type)",
        [vehicleNumber, validUserId, vType]
      );

      const [sessionResult]: any = await connection.query(
        "INSERT INTO Session (license_plate, entry_time, duration) VALUES (?, ?, ?)",
        [vehicleNumber, entryTime || now, duration]
      );
      const sessionId = sessionResult.insertId;

      await connection.query(
        "INSERT INTO Session_Slot (session_id, slot_id) VALUES (?, ?)",
        [sessionId, slotId]
      );

      await connection.query(
        "INSERT INTO Payment (session_id, base_rate, final_fee) VALUES (?, ?, ?)",
        [sessionId, 5, fee]
      );

      await connection.query(
        "INSERT INTO SYS_ACT_Log (user_id, actions, timestamp) VALUES (?, ?, ?)",
        [validUserId, `Customer reserved slot ${slotId} with ${paymentMethod}`, now]
      );

      await connection.commit();
      res.json({ success: true, sessionId: sessionId.toString() });
    } catch (err: any) {
      if (connection) await connection.rollback();
      res.status(500).json({ error: err.message });
    } finally {
      if (connection) connection.release();
    }
  });

  app.get("/api/violations", async (req, res) => {
    try {
      const [rows] = await pool.query(
        "SELECT CAST(violation_id AS CHAR) as id, vehicleNumber, violation_type as type, status FROM Violations ORDER BY status ASC, violation_id DESC"
      );
      res.json(
        (rows as any[]).map((r: any) => ({ ...r, timestamp: new Date().toISOString() }))
      );
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  app.post("/api/violations", async (req, res) => {
    const { vehicleNumber, type, penaltyScore, details } = req.body;
    try {
      const [result]: any = await pool.query(
        "INSERT INTO Violations (vehicleNumber, violation_type) VALUES (?, ?)",
        [vehicleNumber, type]
      );
      res.json({ success: true, id: result.insertId.toString() });
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  app.post("/api/violations/resolve", async (req, res) => {
    const { violationId } = req.body;
    try {
      await pool.query(
        "UPDATE Violations SET status = 'resolved' WHERE violation_id = ?",
        [violationId]
      );
      res.json({ success: true });
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  app.get("/api/blacklist", async (req, res) => {
    try {
      const [rows] = await pool.query(
        "SELECT CAST(blacklist_id AS CHAR) as id, v.vehicleNumber, b.reason, b.date_added as timestamp FROM Blacklist b JOIN Violations v ON b.violation_id = v.violation_id"
      );
      res.json(rows);
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  app.post("/api/blacklist", async (req, res) => {
    const { vehicleNumber, reason } = req.body;
    let connection;
    try {
      connection = await pool.getConnection();
      await connection.beginTransaction();

      await connection.query("INSERT IGNORE INTO Vehicles (license_plate) VALUES (?)", [vehicleNumber]);

      const [violationResult]: any = await connection.query(
        "INSERT INTO Violations (vehicleNumber, violation_type) VALUES (?, 'Blacklisted')",
        [vehicleNumber]
      );
      const violationId = violationResult.insertId;

      await connection.query(
        "INSERT INTO Blacklist (violation_id, reason, date_added) VALUES (?, ?, ?)",
        [violationId, reason, new Date()]
      );

      await connection.commit();
      res.json({ success: true });
    } catch (err: any) {
      if (connection) await connection.rollback();
      res.status(500).json({ error: err.message });
    } finally {
      if (connection) connection.release();
    }
  });

  app.post("/api/blacklist/remove", async (req, res) => {
    const { vehicleNumber } = req.body;
    try {
      await pool.query(
        "DELETE Blacklist FROM Blacklist INNER JOIN Violations ON Blacklist.violation_id = Violations.violation_id WHERE Violations.vehicleNumber = ? AND Violations.violation_type IN ('Blacklisted', 'Admin Blacklist Override')",
        [vehicleNumber]
      );
      await pool.query(
        "DELETE FROM Violations WHERE vehicleNumber = ? AND violation_type IN ('Blacklisted', 'Admin Blacklist Override')",
        [vehicleNumber]
      );
      res.json({ success: true });
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  app.get("/api/reviews", async (req, res) => {
    try {
      const [rows] = await pool.query(
        "SELECT CAST(review_id AS CHAR) as id, CAST(user_id AS CHAR) as userId, text, submit_date as timestamp, sentiment_score as sentimentScore, rating_score as ratingScore, CASE WHEN rating_score >= 4 THEN 'good' WHEN rating_score = 3 THEN 'average' ELSE 'bad' END as rating FROM Review ORDER BY submit_date DESC"
      );
      res.json(rows || []);
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  app.post("/api/reviews", async (req, res) => {
    const { userId, rating, text, sentimentScore } = req.body;
    const ratingScore = rating === "good" ? 5 : rating === "average" ? 3 : 1;
    
    const parsedUserId = parseInt(userId);
    const validUserId = isNaN(parsedUserId) ? null : parsedUserId;
    try {
      const [result]: any = await pool.query(
        "INSERT INTO Review (user_id, text, submit_date, sentiment_score, rating_score) VALUES (?, ?, ?, ?, ?)",
        [validUserId, text, new Date(), sentimentScore, ratingScore]
      );
      res.json({ success: true, id: result.insertId.toString() });
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  app.get("/api/transactions", async (req, res) => {
    try {
      const [rows] = await pool.query(
        "SELECT CAST(p.slip_id AS CHAR) as id, CAST(s.session_id AS CHAR) as sessionId, CAST(v.user_id AS CHAR) as userId, p.final_fee as amount, s.exit_time as timestamp, 'system' as method, 'completed' as status FROM Payment p JOIN Session s ON p.session_id = s.session_id JOIN Vehicles v ON s.license_plate = v.license_plate ORDER BY s.exit_time DESC LIMIT 100"
      );
      res.json(rows || []);
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  app.post("/api/sessions/complete", async (req, res) => {
    const { sessionId, amount, method, trxId, userId } = req.body;
    
    const parsedUserId = parseInt(userId);
    const validUserId = isNaN(parsedUserId) ? null : parsedUserId;
    const now = new Date();
    let connection;

    try {
      const [sessionRows]: any = await pool.query(
        "SELECT ss.slot_id as slotId, s.license_plate as vehicleNumber FROM Session s JOIN Session_Slot ss ON s.session_id = ss.session_id WHERE s.session_id = ?",
        [sessionId]
      );

      if (sessionRows.length === 0) {
        return res.status(404).json({ error: "Session not found" });
      }
      const session = sessionRows[0];

      connection = await pool.getConnection();
      await connection.beginTransaction();

      await connection.query("UPDATE Session SET exit_time = ? WHERE session_id = ?", [now, sessionId]);
      await connection.query("UPDATE Slot SET occupancy = 0 WHERE slot_id = ?", [session.slotId]);
      await connection.query(
        "INSERT INTO SYS_ACT_Log (user_id, actions, timestamp) VALUES (?, ?, ?)",
        [validUserId, `Payment Processed $${amount} for ${session.vehicleNumber}`, now]
      );
      await connection.query(
        "INSERT INTO Payment (session_id, base_rate, final_fee) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE final_fee = VALUES(final_fee)",
        [sessionId, 5, amount]
      );

      await connection.commit();
      res.json({ success: true });
    } catch (err: any) {
      if (connection) await connection.rollback();
      res.status(500).json({ error: err.message });
    } finally {
      if (connection) connection.release();
    }
  });

  app.post("/api/sessions/override", async (req, res) => {
    const {
      sessionId,
      action,
      newSlotId,
      penaltyAmount,
      addToBlacklist,
      blacklistReason,
      userId,
    } = req.body;
    
    const parsedUserId = parseInt(userId);
    const validUserId = isNaN(parsedUserId) ? null : parsedUserId;
    const now = new Date();
    let connection;

    try {
      const [sessionRows]: any = await pool.query(
        "SELECT ss.slot_id as slotId, s.license_plate as vehicleNumber FROM Session s JOIN Session_Slot ss ON s.session_id = ss.session_id WHERE s.session_id = ?",
        [sessionId]
      );

      if (sessionRows.length === 0) {
        return res.status(404).json({ error: "Session not found" });
      }
      const session = sessionRows[0];

      connection = await pool.getConnection();
      await connection.beginTransaction();

      if (action === "relocate" && newSlotId) {
        await connection.query("UPDATE Slot SET occupancy = 0 WHERE slot_id = ?", [session.slotId]);
        await connection.query("UPDATE Slot SET occupancy = 1 WHERE slot_id = ?", [newSlotId]);
        await connection.query("UPDATE Session_Slot SET slot_id = ? WHERE session_id = ?", [newSlotId, sessionId]);
        await connection.query(
          "INSERT INTO SYS_ACT_Log (user_id, actions, timestamp) VALUES (?, ?, ?)",
          [validUserId, `Admin relocated vehicle ${session.vehicleNumber} from ${session.slotId} to ${newSlotId}`, now]
        );
      } else if (action === "end") {
        await connection.query("UPDATE Session SET exit_time = ? WHERE session_id = ?", [now, sessionId]);
        await connection.query("UPDATE Slot SET occupancy = 0 WHERE slot_id = ?", [session.slotId]);
        const fee = penaltyAmount || 0;
        const [paymentResult]: any = await connection.query(
          "INSERT INTO Payment (session_id, base_rate, final_fee) VALUES (?, ?, ?)",
          [sessionId, 5, fee]
        );
        const slipId = paymentResult.insertId;

        if (addToBlacklist) {
          const [violationResult]: any = await connection.query(
            "INSERT INTO Violations (vehicleNumber, slip_id, violation_type) VALUES (?, ?, 'Blacklisted')",
            [session.vehicleNumber, slipId]
          );
          const violationId = violationResult.insertId;
          await connection.query(
            "INSERT INTO Blacklist (violation_id, reason, date_added) VALUES (?, ?, ?)",
            [violationId, blacklistReason || "Admin Override", now]
          );
        }
        await connection.query(
          "INSERT INTO SYS_ACT_Log (user_id, actions, timestamp) VALUES (?, ?, ?)",
          [validUserId, `Admin ended session for ${session.vehicleNumber} with penalty $${fee}`, now]
        );
      }

      if (action === "relocate" && addToBlacklist) {
        const [violationResult]: any = await connection.query(
          "INSERT INTO Violations (vehicleNumber, violation_type) VALUES (?, 'Blacklisted')",
          [session.vehicleNumber]
        );
        const violationId = violationResult.insertId;
        await connection.query(
          "INSERT INTO Blacklist (violation_id, reason, date_added) VALUES (?, ?, ?)",
          [violationId, blacklistReason || "Admin Override", now]
        );
      }

      await connection.commit();
      res.json({ success: true });
    } catch (err: any) {
      if (connection) await connection.rollback();
      res.status(500).json({ error: err.message });
    } finally {
      if (connection) connection.release();
    }
  });

  app.get("/api/users/:uid/dashboard", async (req, res) => {
    const { uid } = req.params;
    try {
      const [rows] = await pool.query(
        "SELECT CAST(s.session_id AS CHAR) as id, s.license_plate as vehicleNumber, s.entry_time as entryTime, s.exit_time as exitTime, CAST(ss.slot_id AS CHAR) as slotId, p.final_fee as fee, CASE WHEN p.slip_id IS NOT NULL THEN 1 ELSE 0 END as isPrepaid, CASE WHEN s.exit_time IS NULL THEN 'active' ELSE 'completed' END as status FROM Session s JOIN Vehicles v ON s.license_plate = v.license_plate LEFT JOIN Session_Slot ss ON s.session_id = ss.session_id LEFT JOIN Payment p ON s.session_id = p.session_id WHERE v.user_id = ? ORDER BY s.entry_time DESC",
        [uid]
      );
      res.json({ sessions: rows });
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  // Generic Handlers for Proxy Bridge
  app.post("/api/generic-set", (req, res) => {
    res.json({ success: true });
  });

  app.post("/api/generic-update", async (req, res) => {
    const { path, data } = req.body;
    try {
      if (path && path.startsWith("users/")) {
        const uid = path.split("/")[1];
        const updates = [];
        const values = [];
        if (data.loyaltyPoints !== undefined) { updates.push("loyaltyPoints = ?"); values.push(data.loyaltyPoints); }
        if (data.violationPoints !== undefined) { updates.push("violationPoints = ?"); values.push(data.violationPoints); }
        if (data.isVIP !== undefined) { updates.push("isVIP = ?"); values.push(data.isVIP ? 1 : 0); }
        
        if (updates.length > 0) {
          values.push(uid);
          await pool.query(`UPDATE User SET ${updates.join(", ")} WHERE user_id = ?`, values);
        }
      }
      res.json({ success: true });
    } catch (e: any) {
      res.status(500).json({ error: e.message });
    }
  });

  app.post("/api/generic-delete", (req, res) => {
    res.json({ success: true });
  });

  app.get("/api/users", async (req, res) => {
    try {
      const [rows] = await pool.query(
        "SELECT CAST(user_id AS CHAR) as uid, user_type as role, username, full_name as name, email, loyaltyPoints, violationPoints, isVIP FROM User"
      );
      res.json(rows || []);
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  app.get("/api/users/:uid", async (req, res) => {
    const { uid } = req.params;
    try {
      const [rows]: any[] = await pool.query(
        "SELECT CAST(user_id AS CHAR) as uid, user_type as role, username, full_name as name, email, loyaltyPoints, violationPoints, isVIP FROM User WHERE user_id = ?",
        [uid]
      );
      res.json(rows[0] || { uid, role: "customer", name: "User" });
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  app.post("/api/register", async (req, res) => {
    const { username, password, name } = req.body;
    try {
      const saltRounds = 10;
      const hashedPassword = await bcrypt.hash(password, saltRounds);

      const [result]: any = await pool.query(
        "INSERT INTO User (password, username, user_type, full_name) VALUES (?, ?, 'Customer', ?)",
        [hashedPassword, username, name]
      );

      res.json({
        success: true,
        uid: result.insertId.toString(),
        role: "Customer",
        username: username,
      });
    } catch (err: any) {
      if (err.code === 'ER_DUP_ENTRY') {
        return res.status(409).json({ error: "Username already exists." });
      }
      res.status(500).json({ error: err.message });
    }
  });

  app.post("/api/login", async (req, res) => {
    const { username, password } = req.body;
    try {
      const [rows]: any[] = await pool.query(
        "SELECT CAST(user_id AS CHAR) as uid, user_type as role, username, password as hash FROM User WHERE username = ?",
        [username]
      );

      if (rows.length === 0) {
        return res.status(401).json({ error: "Invalid credentials" });
      }
      const user = rows[0];

      const passwordMatch = await bcrypt.compare(password, user.hash);

      if (!passwordMatch) {
        return res.status(401).json({ error: "Invalid credentials" });
      }

      res.json({ uid: user.uid.toString(), role: user.role, username: user.username });
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  app.post("/api/users/update", async (req, res) => {
    const { uid, username, role, name } = req.body;
    const roleMap: any = {
      admin: "Admin",
      manager: "Manager",
      staff: "Staff",
      customer: "Customer",
    };
    const dbRole = roleMap[role?.toLowerCase()] || "Customer";
    try {
      if (name) {
        await pool.query(
          "UPDATE User SET username = ?, user_type = ?, full_name = ? WHERE user_id = ?",
          [username, dbRole, name, uid]
        );
      } else {
        await pool.query(
          "UPDATE User SET username = ?, user_type = ? WHERE user_id = ?",
          [username, dbRole, uid]
        );
      }
      res.json({ success: true });
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  app.post("/api/users/update-role", async (req, res) => {
    const { uid, role } = req.body;
    const roleMap: any = {
      admin: "Admin",
      manager: "Manager",
      staff: "Staff",
      customer: "Customer",
    };
    const dbRole = roleMap[role?.toLowerCase()] || "Customer";
    try {
      await pool.query(
        "UPDATE User SET user_type = ? WHERE user_id = ?",
        [dbRole, uid]
      );
      res.json({ success: true });
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  app.post("/api/users/delete", async (req, res) => {
    const { uid } = req.body;
    try {
      await pool.query("DELETE FROM User WHERE user_id = ?", [uid]);
      res.json({ success: true });
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  app.get("/api/logs", async (req, res) => {
    try {
      const [rows] = await pool.query(
        "SELECT CAST(log_id AS CHAR) as id, COALESCE(CAST(user_id AS CHAR), 'System') as userId, actions as action, timestamp FROM SYS_ACT_Log ORDER BY timestamp DESC LIMIT 100"
      );
      res.json(rows || []);
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  app.post("/api/logs", async (req, res) => {
    const { userId, action, details } = req.body;
    
    const parsedUserId = parseInt(userId);
    const validUserId = isNaN(parsedUserId) ? null : parsedUserId;
    try {
      await pool.query(
        "INSERT INTO SYS_ACT_Log (user_id, actions, timestamp) VALUES (?, ?, ?)",
        [validUserId, `${action}: ${details}`, new Date()]
      );
      res.json({ success: true });
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  // Keep compatibility
  app.get("/api/activityLogs", async (req, res) => {
    try {
      const [rows] = await pool.query(
        "SELECT CAST(log_id AS CHAR) as id, COALESCE(CAST(user_id AS CHAR), 'System') as userId, actions as action, timestamp FROM SYS_ACT_Log ORDER BY timestamp DESC LIMIT 100"
      );
      res.json(rows || []);
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  app.post("/api/activityLogs", async (req, res) => {
    const { userId, action, details } = req.body;
    
    const parsedUserId = parseInt(userId);
    const validUserId = isNaN(parsedUserId) ? null : parsedUserId;
    try {
      await pool.query(
        "INSERT INTO SYS_ACT_Log (user_id, actions, timestamp) VALUES (?, ?, ?)",
        [validUserId, `${action}: ${details}`, new Date()]
      );
      res.json({ success: true });
    } catch (err: any) {
      res.status(500).json({ error: err.message });
    }
  });

  // Vite middleware for development
  if (process.env.NODE_ENV !== "production") {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: "spa",
    });
    console.log("[SERVER] Vite middleware initialized successfully");
    app.use(vite.middlewares);
  } else {
    const distPath = path.join(process.cwd(), "dist");
    app.use(express.static(distPath));
    app.get("*", (req, res) => {
      res.sendFile(path.join(distPath, "index.html"));
    });
  }

  app
    .listen(PORT, "0.0.0.0", () => {
      console.log(`[SERVER] Success: Running on http://0.0.0.0:${PORT}`);
    })
    .on("error", (err) => {
      console.error("[SERVER] Error starting server:", err);
    });
}

startServer().catch((err) => {
  console.error("[SERVER] Fatal error in startServer:", err);
});

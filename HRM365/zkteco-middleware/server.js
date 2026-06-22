const http = require('http');

/**
 * ZKTeco M2 Core System Integration Gateway Client
 * Enforces strict error mitigation, reconnection, and mapping configurations.
 * 
 * Note: In a real environment, you would run `npm install zk-attendance-sdk`
 */
const ZKLib = require('node-zklib');

const TERMINAL_IP = process.env.ZK_TERMINAL_IP || '192.168.1.213';
const TERMINAL_PORT = Number(process.env.ZK_TERMINAL_PORT || 4370);
// We changed this to point to our local XAMPP instance for testing
const CORE_HRM_INGEST_URL = 'http://localhost/HRM365/api/attendance/raw-punch.php';

class HardwareIntegrationService {
    constructor(ip, port) {
        this.ip = ip;
        this.port = port;
        this.terminalInstance = null;
        this.isProcessing = false;
    }

    async bootstrapPipeline() {
        try {
            console.log(`[INIT] Opening Socket to ZKTeco M2 at: ${this.ip}:${this.port}`);
            // Initialize zklib
            this.terminalInstance = new ZKLib(this.ip, this.port, 10000, 4000);
            await this.terminalInstance.createSocket();
            console.log('--- CONNECTION ESTABLISHED SUCCESSFULLY ---');
            
            // Sync terminal clock to maintain system synchronization
            await this.terminalInstance.setTime(new Date());
            
            // Execute recurring retrieval sequence
            this.executeIngestionCycle();
        } catch (err) {
            console.error(`[CRITICAL] Connection Refused: ${err.message}`);
            this.retryAfterDelay();
        }
    }

    async executeIngestionCycle() {
        if (this.isProcessing) return;
        this.isProcessing = true;
        
        try {
            console.log('\n[DATA] Executing raw transaction log pull sequence...');
            const records = await this.terminalInstance.getAttendances();
            
            if (records && records.data && records.data.length > 0) {
                console.log(`[DATA] Discovered ${records.data.length} pending entries.`);
                for (const row of records.data) {
                    await this.transformAndForward(row);
                }
            } else {
                console.log('[DATA] Zero unhandled transaction lines present on hardware.');
            }
        } catch (error) {
            console.error(`[ERROR] Processing Failure during stream extraction: ${error.message}`);
        } finally {
            this.isProcessing = false;
            // Schedule subsequent check window
            setTimeout(() => this.executeIngestionCycle(), 60000);
        }
    }

    async transformAndForward(rawRecord) {
        const structuralPayload = {
            biometricUserId: String(rawRecord.userId),
            timestamp: rawRecord.attTime,
            punchDirection: rawRecord.recordType === 0 ? 'CHECK_IN' : 'CHECK_OUT',
            hardwareMechanism: this.resolveVerificationType(rawRecord.verificationType)
        };
        
        console.log(`[FORWARD] Payload mapped: ${JSON.stringify(structuralPayload)}`);

        // Forward to PHP API using native fetch
        try {
            const response = await fetch(CORE_HRM_INGEST_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(structuralPayload)
            });
            const result = await response.json();
            console.log(`[API RESPONSE]`, result);
        } catch (err) {
            console.error(`[API ERROR] Failed to push to PHP backend:`, err.message);
        }
    }

    resolveVerificationType(typeByte) {
        const lookup = {
            1: 'FINGERPRINT_BIOMETRIC',
            2: 'PIN_PASSWORD_AUTHENTICATION',
            4: 'RFID_PROXIMITY_CARD',
            15: 'FACE_RECOGNITION_BIOMETRIC'
        };
        return lookup[typeByte] || 'UNKNOWN_HARDWARE_INPUT';
    }

    retryAfterDelay() {
        console.log('[RETRY] Initiating link retry loop in 30 seconds...');
        setTimeout(() => this.bootstrapPipeline(), 30000);
    }
}

// System execution block
console.log("Starting ZKTeco Integration Middleware...");
const integrationWorker = new HardwareIntegrationService(TERMINAL_IP, TERMINAL_PORT);
integrationWorker.bootstrapPipeline();

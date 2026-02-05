/**
 * Shell Gate Terminal Gateway
 *
 * WebSocket server handling PTY sessions for Shell Gate.
 * Validates JWT tokens and manages terminal sessions.
 */

import 'dotenv/config';
import { createServer } from 'http';
import { WebSocketServer } from 'ws';
import { verifyToken } from './lib/jwt.js';
import { PtyManager } from './lib/pty-manager.js';
import { logger } from './lib/logger.js';

// Configuration from environment
const config = {
    port: parseInt(process.env.PORT || '7681', 10),
    host: process.env.HOST || '127.0.0.1',
    jwtSecret: process.env.JWT_SECRET || process.env.APP_KEY,
    allowedOrigins: (process.env.ALLOWED_ORIGINS || '').split(',').filter(Boolean),
    maxSessions: parseInt(process.env.MAX_SESSIONS || '100', 10),
    sessionTimeout: parseInt(process.env.SESSION_TIMEOUT || '0', 10),
    idleTimeout: parseInt(process.env.IDLE_TIMEOUT || '1800', 10),
    shell: process.env.SHELL || '/bin/bash',
    defaultCwd: process.env.DEFAULT_CWD || process.env.HOME,
};

// Validate required config
if (!config.jwtSecret) {
    logger.error('JWT_SECRET environment variable is required');
    process.exit(1);
}

// Initialize PTY manager
const ptyManager = new PtyManager(config);

// Create HTTP server for health checks
const httpServer = createServer((req, res) => {
    if (req.url === '/health' && req.method === 'GET') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
            status: 'ok',
            activeSessions: ptyManager.getActiveSessionCount(),
            maxSessions: config.maxSessions,
            uptime: process.uptime(),
        }));
        return;
    }

    res.writeHead(404);
    res.end('Not Found');
});

// Create WebSocket server
const wss = new WebSocketServer({
    server: httpServer,
    verifyClient: (info, callback) => {
        // Check origin if configured
        if (config.allowedOrigins.length > 0) {
            const origin = info.origin || info.req.headers.origin;
            if (!origin || !config.allowedOrigins.includes(origin)) {
                logger.warn('Connection rejected: invalid origin', { origin });
                callback(false, 403, 'Origin not allowed');
                return;
            }
        }

        callback(true);
    },
});

// WebSocket close codes
const WS_CLOSE_CODES = {
    NORMAL: 1000,
    INVALID_TOKEN: 4001,
    TOKEN_EXPIRED: 4002,
    IP_MISMATCH: 4003,
    ORIGIN_NOT_ALLOWED: 4004,
    MAX_SESSIONS: 4005,
    INTERNAL_ERROR: 4006,
};

// Handle WebSocket connections
wss.on('connection', async (ws, req) => {
    const clientIp = req.headers['x-forwarded-for']?.split(',')[0]?.trim()
        || req.headers['x-real-ip']
        || req.socket.remoteAddress;

    const userAgent = req.headers['user-agent'];

    logger.info('New connection', { ip: clientIp });

    // Extract token from query string
    const url = new URL(req.url, `http://${req.headers.host}`);
    const token = url.searchParams.get('token');

    if (!token) {
        logger.warn('Connection rejected: no token', { ip: clientIp });
        ws.close(WS_CLOSE_CODES.INVALID_TOKEN, 'Token required');
        return;
    }

    // Verify JWT token
    let payload;
    try {
        payload = verifyToken(token, config.jwtSecret, clientIp, userAgent);
    } catch (error) {
        logger.warn('Token verification failed', {
            ip: clientIp,
            error: error.message,
        });

        const code = error.message.includes('expired')
            ? WS_CLOSE_CODES.TOKEN_EXPIRED
            : error.message.includes('mismatch')
                ? WS_CLOSE_CODES.IP_MISMATCH
                : WS_CLOSE_CODES.INVALID_TOKEN;

        ws.close(code, error.message);
        return;
    }

    // Check session limit
    if (ptyManager.getActiveSessionCount() >= config.maxSessions) {
        logger.warn('Max sessions reached', {
            ip: clientIp,
            current: ptyManager.getActiveSessionCount(),
            max: config.maxSessions,
        });
        ws.close(WS_CLOSE_CODES.MAX_SESSIONS, 'Maximum sessions reached');
        return;
    }

    // Create PTY session
    const sessionId = payload.jti || payload.session_id;
    const cwd = payload.cwd || config.defaultCwd;

    try {
        const pty = ptyManager.createSession(sessionId, ws, {
            shell: config.shell,
            cwd,
            cols: payload.cols || 120,
            rows: payload.rows || 30,
            userId: payload.sub,
        });

        logger.info('Session created', {
            sessionId,
            userId: payload.sub,
            ip: clientIp,
        });

        // Handle incoming messages
        ws.on('message', (data) => {
            try {
                // Try to parse as JSON (for control messages)
                const message = JSON.parse(data.toString());

                if (message.type === 'resize' && message.cols && message.rows) {
                    ptyManager.resizeSession(sessionId, message.cols, message.rows);
                }
            } catch {
                // Not JSON - treat as terminal input
                ptyManager.writeToSession(sessionId, data.toString());
            }
        });

        // Handle connection close
        ws.on('close', (code, reason) => {
            logger.info('Connection closed', {
                sessionId,
                code,
                reason: reason?.toString(),
            });
            ptyManager.destroySession(sessionId, 'client_disconnect');
        });

        // Handle errors
        ws.on('error', (error) => {
            logger.error('WebSocket error', { sessionId, error: error.message });
            ptyManager.destroySession(sessionId, 'error');
        });

    } catch (error) {
        logger.error('Failed to create session', {
            sessionId,
            error: error.message,
        });
        ws.close(WS_CLOSE_CODES.INTERNAL_ERROR, 'Failed to create session');
    }
});

// Graceful shutdown
const shutdown = () => {
    logger.info('Shutting down...');

    // Close all PTY sessions
    ptyManager.destroyAllSessions('shutdown');

    // Close WebSocket server
    wss.close(() => {
        httpServer.close(() => {
            logger.info('Server stopped');
            process.exit(0);
        });
    });

    // Force exit after timeout
    setTimeout(() => {
        logger.warn('Forced shutdown');
        process.exit(1);
    }, 5000);
};

process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);

// Start server
httpServer.listen(config.port, config.host, () => {
    logger.info(`Shell Gate Gateway started`, {
        host: config.host,
        port: config.port,
        maxSessions: config.maxSessions,
    });
});

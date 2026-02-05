/**
 * PTY Session Manager
 *
 * Manages pseudo-terminal sessions for connected clients.
 */

import pty from 'node-pty';
import { logger } from './logger.js';

/**
 * Session data structure
 * @typedef {Object} Session
 * @property {import('node-pty').IPty} pty - PTY instance
 * @property {import('ws').WebSocket} ws - WebSocket connection
 * @property {number} userId - User ID
 * @property {number} createdAt - Session creation timestamp
 * @property {number} lastActivity - Last activity timestamp
 * @property {NodeJS.Timeout|null} idleTimer - Idle timeout timer
 * @property {NodeJS.Timeout|null} sessionTimer - Session timeout timer
 */

export class PtyManager {
    /** @type {Map<string, Session>} */
    #sessions = new Map();

    /** @type {Object} */
    #config;

    /**
     * @param {Object} config
     */
    constructor(config) {
        this.#config = config;
    }

    /**
     * Create a new PTY session.
     *
     * @param {string} sessionId - Unique session ID
     * @param {import('ws').WebSocket} ws - WebSocket connection
     * @param {Object} options - Session options
     * @returns {import('node-pty').IPty}
     */
    createSession(sessionId, ws, options = {}) {
        const {
            shell = this.#config.shell || '/bin/bash',
            cwd = this.#config.defaultCwd || process.env.HOME,
            cols = 120,
            rows = 30,
            userId = null,
        } = options;

        // Create PTY
        const ptyProcess = pty.spawn(shell, [], {
            name: 'xterm-256color',
            cols,
            rows,
            cwd,
            env: {
                ...process.env,
                TERM: 'xterm-256color',
                COLORTERM: 'truecolor',
                LANG: 'en_US.UTF-8',
            },
        });

        // Forward PTY output to WebSocket
        ptyProcess.onData((data) => {
            if (ws.readyState === ws.OPEN) {
                ws.send(data);
                this.#updateActivity(sessionId);
            }
        });

        // Handle PTY exit
        ptyProcess.onExit(({ exitCode, signal }) => {
            logger.info('PTY exited', { sessionId, exitCode, signal });
            this.destroySession(sessionId, 'pty_exit');
        });

        // Create session object
        const session = {
            pty: ptyProcess,
            ws,
            userId,
            createdAt: Date.now(),
            lastActivity: Date.now(),
            idleTimer: null,
            sessionTimer: null,
        };

        // Set up timeouts
        if (this.#config.idleTimeout > 0) {
            session.idleTimer = this.#startIdleTimer(sessionId);
        }

        if (this.#config.sessionTimeout > 0) {
            session.sessionTimer = setTimeout(() => {
                logger.info('Session timeout', { sessionId });
                this.destroySession(sessionId, 'session_timeout');
            }, this.#config.sessionTimeout * 1000);
        }

        this.#sessions.set(sessionId, session);

        return ptyProcess;
    }

    /**
     * Write data to PTY session.
     *
     * @param {string} sessionId
     * @param {string} data
     */
    writeToSession(sessionId, data) {
        const session = this.#sessions.get(sessionId);
        if (session) {
            session.pty.write(data);
            this.#updateActivity(sessionId);
        }
    }

    /**
     * Resize PTY session.
     *
     * @param {string} sessionId
     * @param {number} cols
     * @param {number} rows
     */
    resizeSession(sessionId, cols, rows) {
        const session = this.#sessions.get(sessionId);
        if (session) {
            session.pty.resize(cols, rows);
            logger.debug('Session resized', { sessionId, cols, rows });
        }
    }

    /**
     * Destroy a PTY session.
     *
     * @param {string} sessionId
     * @param {string} reason
     */
    destroySession(sessionId, reason = 'unknown') {
        const session = this.#sessions.get(sessionId);
        if (!session) return;

        // Clear timers
        if (session.idleTimer) clearTimeout(session.idleTimer);
        if (session.sessionTimer) clearTimeout(session.sessionTimer);

        // Kill PTY
        try {
            session.pty.kill();
        } catch (error) {
            logger.debug('PTY kill error (may already be dead)', { error: error.message });
        }

        // Close WebSocket if still open
        if (session.ws.readyState === session.ws.OPEN) {
            session.ws.close(1000, reason);
        }

        this.#sessions.delete(sessionId);

        logger.info('Session destroyed', { sessionId, reason });

        // TODO: Optional callback to Laravel to update session end time
        // this.#notifySessionEnd(sessionId, reason);
    }

    /**
     * Destroy all sessions.
     *
     * @param {string} reason
     */
    destroyAllSessions(reason = 'shutdown') {
        for (const sessionId of this.#sessions.keys()) {
            this.destroySession(sessionId, reason);
        }
    }

    /**
     * Get active session count.
     *
     * @returns {number}
     */
    getActiveSessionCount() {
        return this.#sessions.size;
    }

    /**
     * Check if session exists.
     *
     * @param {string} sessionId
     * @returns {boolean}
     */
    hasSession(sessionId) {
        return this.#sessions.has(sessionId);
    }

    /**
     * Update session activity timestamp.
     *
     * @param {string} sessionId
     */
    #updateActivity(sessionId) {
        const session = this.#sessions.get(sessionId);
        if (session) {
            session.lastActivity = Date.now();

            // Reset idle timer
            if (session.idleTimer) {
                clearTimeout(session.idleTimer);
                session.idleTimer = this.#startIdleTimer(sessionId);
            }
        }
    }

    /**
     * Start idle timeout timer.
     *
     * @param {string} sessionId
     * @returns {NodeJS.Timeout}
     */
    #startIdleTimer(sessionId) {
        return setTimeout(() => {
            logger.info('Session idle timeout', { sessionId });
            this.destroySession(sessionId, 'idle_timeout');
        }, this.#config.idleTimeout * 1000);
    }
}

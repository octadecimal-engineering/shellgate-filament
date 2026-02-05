/**
 * Simple logger for Shell Gate Gateway
 */

const LOG_LEVELS = {
    debug: 0,
    info: 1,
    warn: 2,
    error: 3,
};

const currentLevel = LOG_LEVELS[process.env.LOG_LEVEL || 'info'] || LOG_LEVELS.info;

/**
 * Format log message as JSON.
 *
 * @param {string} level
 * @param {string} message
 * @param {Object} data
 * @returns {string}
 */
function formatLog(level, message, data = {}) {
    return JSON.stringify({
        timestamp: new Date().toISOString(),
        level,
        message,
        ...data,
    });
}

export const logger = {
    /**
     * Log debug message.
     * @param {string} message
     * @param {Object} data
     */
    debug(message, data = {}) {
        if (currentLevel <= LOG_LEVELS.debug) {
            console.log(formatLog('debug', message, data));
        }
    },

    /**
     * Log info message.
     * @param {string} message
     * @param {Object} data
     */
    info(message, data = {}) {
        if (currentLevel <= LOG_LEVELS.info) {
            console.log(formatLog('info', message, data));
        }
    },

    /**
     * Log warning message.
     * @param {string} message
     * @param {Object} data
     */
    warn(message, data = {}) {
        if (currentLevel <= LOG_LEVELS.warn) {
            console.warn(formatLog('warn', message, data));
        }
    },

    /**
     * Log error message.
     * @param {string} message
     * @param {Object} data
     */
    error(message, data = {}) {
        if (currentLevel <= LOG_LEVELS.error) {
            console.error(formatLog('error', message, data));
        }
    },
};

/**
 * JWT Token Verification
 */

import jwt from 'jsonwebtoken';
import crypto from 'crypto';

/**
 * Verify JWT token and validate bindings.
 *
 * @param {string} token - JWT token
 * @param {string} secret - JWT secret
 * @param {string|null} clientIp - Client IP for validation
 * @param {string|null} userAgent - User-Agent for validation
 * @returns {object} Decoded payload
 * @throws {Error} If token is invalid or bindings don't match
 */
export function verifyToken(token, secret, clientIp = null, userAgent = null) {
    // Decode and verify token
    const payload = jwt.verify(token, secret, {
        algorithms: ['HS256'],
    });

    // Validate IP binding if present
    if (payload.ip && clientIp) {
        if (payload.ip !== clientIp) {
            throw new Error('IP address mismatch');
        }
    }

    // Validate User-Agent binding if present
    if (payload.ua_hash && userAgent) {
        const hash = hashUserAgent(userAgent);
        if (payload.ua_hash !== hash) {
            throw new Error('User-Agent mismatch');
        }
    }

    return payload;
}

/**
 * Hash User-Agent string for comparison.
 *
 * @param {string} userAgent
 * @returns {string}
 */
function hashUserAgent(userAgent) {
    return crypto.createHash('sha256').update(userAgent).digest('hex');
}

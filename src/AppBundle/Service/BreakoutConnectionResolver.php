<?php

namespace AppBundle\Service;

/**
 * Resolves the *effective* connection target for breakout SGBD queries.
 *
 * Two modes, controlled by BREAKOUT_CONNECTION_MODE:
 *
 *   direct  - Pass through host/port as configured by the operator
 *             (cspro_dictionaries_schema row, or the "Add Configuration" form).
 *             Suitable when CSWeb can reach the DB directly:
 *               - co-located on the same VPS / VPC
 *               - DB exposed on a public IP (ideally behind IP whitelist)
 *               - private network (10.x, 192.168.x) reachable from the container
 *
 *   tunnel  - Override host/port to the local end of the SSH tunnel
 *             established by docker-entrypoint.sh. The tunnel forwards
 *             127.0.0.1:BREAKOUT_TUNNEL_LOCAL_PORT (inside the container) to
 *             BREAKOUT_TUNNEL_REMOTE_HOST:BREAKOUT_TUNNEL_REMOTE_PORT on
 *             BREAKOUT_SSH_HOST. Operators who reach the DB through SSH
 *             (DB port not publicly exposed) use this mode.
 *
 * In tunnel mode the host stored in cspro_dictionaries_schema is *cosmetic* —
 * the resolver always rewrites to 127.0.0.1:tunnel-port at connection time.
 * This keeps existing UIs working without forcing operators to enter "127.0.0.1".
 */
final class BreakoutConnectionResolver
{
    /**
     * @param array{host_name?:string|null, port?:int|string|null, db_type?:string|null} $row
     *        Row read from cspro_dictionaries_schema.
     * @return array{host:string, port:?int, mode:string}
     */
    public static function resolve(array $row): array
    {
        $mode = strtolower((string) (getenv('BREAKOUT_CONNECTION_MODE') ?: 'direct'));

        if ($mode === 'tunnel') {
            $localPort = (int) (getenv('BREAKOUT_TUNNEL_LOCAL_PORT') ?: 13306);
            return [
                'host' => '127.0.0.1',
                'port' => $localPort,
                'mode' => 'tunnel',
            ];
        }

        // direct mode (default)
        $port = $row['port'] ?? null;
        return [
            'host' => (string) ($row['host_name'] ?? ''),
            'port' => $port !== null && $port !== '' ? (int) $port : null,
            'mode' => 'direct',
        ];
    }
}

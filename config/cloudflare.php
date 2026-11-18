<?php

/*
| Direcciones de Cloudflare (https://www.cloudflare.com/ips/). Con TRUSTED_PROXIES=cloudflare en el .env, Laravel
| confía solo en ellas para leer la IP real de quien entra (X-Forwarded-For) y el https. Sin esto, todos los usuarios
| se verían con la IP de Cloudflare y el límite de intentos de acceso (que va por IP) los bloquearía juntos.
| Cloudflare casi nunca cambia esta lista; si lo hace, se actualiza aquí.
*/
return [
    'ips' => [
        // IPv4
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        // IPv6
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ],
];

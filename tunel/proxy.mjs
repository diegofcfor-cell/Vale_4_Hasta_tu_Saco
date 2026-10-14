// Junta la web de Laravel y el WebSocket de Reverb en un solo puerto, para
// que alcance con un solo túnel: los pedidos normales van a `php artisan serve`
// y las conexiones WebSocket van a `php artisan reverb:start`.
//
//   node tunel/proxy.mjs       (o `composer run mesa`, que levanta todo junto)
//
// Después abrí http://localhost:8888 o pasale ese puerto al túnel.

import http from 'node:http';
import net from 'node:net';

const PUERTO = Number(process.env.MESA_PORT ?? 8888);
const WEB = { host: '127.0.0.1', port: Number(process.env.WEB_PORT ?? 8000) };
const REVERB = { host: '127.0.0.1', port: Number(process.env.REVERB_PORT ?? 8080) };

function encabezadosReenviados(req) {
    return {
        'x-forwarded-for': req.headers['x-forwarded-for'] ?? req.socket.remoteAddress,
        'x-forwarded-proto': req.headers['x-forwarded-proto'] ?? 'http',
        'x-forwarded-host': req.headers['x-forwarded-host'] ?? req.headers.host,
    };
}

const servidor = http.createServer((req, res) => {
    const pedido = http.request(
        { ...WEB, method: req.method, path: req.url, headers: { ...req.headers, ...encabezadosReenviados(req) } },
        (respuesta) => {
            res.writeHead(respuesta.statusCode, respuesta.headers);
            respuesta.pipe(res);
        },
    );

    pedido.on('error', () => {
        res.writeHead(502, { 'content-type': 'text/plain; charset=utf-8' });
        res.end(`Laravel no responde en el puerto ${WEB.port}. ¿Está corriendo php artisan serve?`);
    });

    req.pipe(pedido);
});

servidor.on('upgrade', (req, socket, cabeza) => {
    const reverb = net.connect(REVERB.port, REVERB.host, () => {
        const lineas = [`${req.method} ${req.url} HTTP/${req.httpVersion}`];
        for (let i = 0; i < req.rawHeaders.length; i += 2) {
            lineas.push(`${req.rawHeaders[i]}: ${req.rawHeaders[i + 1]}`);
        }
        reverb.write(lineas.join('\r\n') + '\r\n\r\n');
        reverb.write(cabeza);
        reverb.pipe(socket);
        socket.pipe(reverb);
    });

    reverb.on('error', () => socket.destroy());
    socket.on('error', () => reverb.destroy());
});

servidor.listen(PUERTO, () => {
    console.log(`Mesa lista en http://localhost:${PUERTO} (web :${WEB.port}, Reverb :${REVERB.port})`);
});

import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import fs from 'fs';
import http from 'http';
import path from 'path';
import { spawn, exec, ChildProcess } from 'child_process';
import {defineConfig, Plugin} from 'vite';

let phpProcess: ChildProcess | null = null;
const PHP_PORT = 8001;

function phpServerPlugin(): Plugin {
  return {
    name: 'vite-plugin-php-server',
    configureServer(server) {
      // Garante que o MariaDB esteja rodando caso esteja instalado
      try {
        exec('pgrep -x mariadbd || pgrep -x mysqld || (/usr/bin/mariadbd-safe --user=root >/dev/null 2>&1 &)', (err) => {
          if (err) {
            // non-fatal
          }
        });
      } catch {
        // ignore
      }

      if (!phpProcess) {
        try {
          phpProcess = spawn('php', ['-S', `127.0.0.1:${PHP_PORT}`, '-t', '.'], {
            stdio: 'inherit',
          });

          phpProcess.on('error', (err) => {
            console.error('PHP process error:', err);
          });
        } catch (err) {
          console.error('Failed to start PHP:', err);
        }

        const cleanup = () => {
          if (phpProcess) {
            try {
              phpProcess.kill();
            } catch {
              // ignore
            }
            phpProcess = null;
          }
        };

        process.on('exit', cleanup);
        process.on('SIGINT', cleanup);
        process.on('SIGTERM', cleanup);
      }

      server.middlewares.use((req, res, next) => {
        const url = req.url || '/';
        const cleanPath = url.split('?')[0];

        // Roteia para o PHP todas as páginas do sistema
        const isPhpRoute =
          cleanPath === '/' ||
          cleanPath.endsWith('.php') ||
          cleanPath.startsWith('/admin') ||
          cleanPath.startsWith('/public') ||
          cleanPath.startsWith('/api') ||
          cleanPath.startsWith('/assets/css') ||
          cleanPath.startsWith('/assets/js') ||
          cleanPath === '/login' ||
          cleanPath === '/logout';

        if (!isPhpRoute) {
          return next();
        }

        const headers = {
          ...req.headers,
          host: `127.0.0.1:${PHP_PORT}`,
          'x-forwarded-proto': 'https',
        };

        const options: http.RequestOptions = {
          hostname: '127.0.0.1',
          port: PHP_PORT,
          path: url,
          method: req.method,
          headers,
        };

        const proxyReq = http.request(options, (proxyRes) => {
          const rawCookies = proxyRes.headers['set-cookie'];
          if (rawCookies) {
            const cookies = Array.isArray(rawCookies) ? rawCookies : [rawCookies];
            proxyRes.headers['set-cookie'] = cookies.map((c) => {
              let cookie = c;
              if (/SameSite=(Lax|Strict)/i.test(cookie)) {
                cookie = cookie.replace(/SameSite=(Lax|Strict)/i, 'SameSite=None');
              } else if (!/SameSite=/i.test(cookie)) {
                cookie += '; SameSite=None';
              }
              if (!/;\s*Secure/i.test(cookie)) {
                cookie += '; Secure';
              }
              if (!/;\s*Partitioned/i.test(cookie)) {
                cookie += '; Partitioned';
              }
              return cookie;
            });
          }

          res.writeHead(proxyRes.statusCode || 200, proxyRes.headers);
          proxyRes.pipe(res);
        });

        proxyReq.on('error', (err) => {
          console.error('[PHP Proxy Error]', err.message);
          res.statusCode = 502;
          res.end('Erro ao comunicar com o servidor PHP.');
        });

        req.pipe(proxyReq);
      });
    },
  };
}

// LINT.IfChange(aistudio_media_plugin)
function aistudioMediaPlugin(): Plugin {
  return {
    name: 'vite-plugin-aistudio-media',
    configureServer(server) {
      server.middlewares.use((req, res, next) => {
        if (req.url && req.url.startsWith('/assets/aistudio/')) {
          const rawPath = req.url.split('?')[0].split('#')[0];
          try {
            const decodedPath = decodeURIComponent(rawPath);
            const relativePath = decodedPath.replace(/^\//, '');
            const aistudioDir = path.resolve(
              __dirname,
              'public',
              'assets',
              'aistudio',
            );
            const filePath = path.resolve(__dirname, 'public', relativePath);
            if (
              filePath.startsWith(aistudioDir + path.sep) &&
              fs.existsSync(filePath) &&
              fs.statSync(filePath).isFile()
            ) {
              const ext = path.extname(filePath).toLowerCase();
              const mimeMap: Record<string, string> = {
                '.jpg': 'image/jpeg',
                '.jpeg': 'image/jpeg',
                '.png': 'image/png',
                '.gif': 'image/gif',
                '.webp': 'image/webp',
                '.svg': 'image/svg+xml',
                '.bmp': 'image/bmp',
                '.ico': 'image/x-icon',
                '.mp4': 'video/mp4',
                '.webm': 'video/webm',
                '.ogv': 'video/ogg',
                '.mp3': 'audio/mpeg',
                '.wav': 'audio/wav',
                '.ogg': 'audio/ogg',
                '.pdf': 'application/pdf',
              };
              res.setHeader(
                'Content-Type',
                mimeMap[ext] || 'application/octet-stream',
              );
              res.setHeader('Cache-Control', 'no-cache');
              fs.createReadStream(filePath).pipe(res);
              return;
            }
          } catch {
            // Fall through if URI decoding or file access fails
          }
        }
        next();
      });
    },
  };
}
// LINT.ThenChange(//depot/google3/java/com/google/alkali/boq/makersuite/applet_dev_service/templates/initializers/react_theme/vite.config.ts:aistudio_media_plugin)

export default defineConfig(() => {
  return {
    plugins: [react(), tailwindcss(), aistudioMediaPlugin(), phpServerPlugin()],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, '.'),
      },
    },
    server: {
      // HMR is disabled in AI Studio via DISABLE_HMR env var.
      // Do not modifyâfile watching is disabled to prevent flickering during agent edits.
      hmr: process.env.DISABLE_HMR !== 'true',
      // Disable file watching when DISABLE_HMR is true to save CPU during agent edits.
      watch: process.env.DISABLE_HMR === 'true' ? null : {},
    },
  };
});

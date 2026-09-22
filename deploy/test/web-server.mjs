import { createReadStream } from "node:fs";
import { stat } from "node:fs/promises";
import { createServer, request as httpRequest } from "node:http";
import { request as httpsRequest } from "node:https";
import { extname, resolve, sep } from "node:path";

const root = resolve("/app/dist");
const port = Number.parseInt(process.env.MARS_WEB_PORT ?? "8080", 10);
const apiOrigin = new URL(process.env.MARS_API_ORIGIN ?? "http://api:8080");
const proxyPrefixes = ["/api/", "/health/", "/openapi/", "/connect/"];

if (!Number.isInteger(port) || port <= 0 || port > 65535) {
  throw new Error("MARS_WEB_PORT must be a valid TCP port.");
}

const securityHeaders = {
  "Content-Security-Policy":
    "default-src 'self'; base-uri 'self'; frame-ancestors 'none'; object-src 'none'; " +
    "script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'",
  "Referrer-Policy": "same-origin",
  "X-Content-Type-Options": "nosniff",
  "X-Frame-Options": "DENY"
};

const server = createServer(async (req, res) => {
  try {
    const url = new URL(req.url ?? "/", "http://mars.test");

    if (proxyPrefixes.some((prefix) => url.pathname.startsWith(prefix))) {
      proxyToApi(req, res, url);
      return;
    }

    if (req.method !== "GET" && req.method !== "HEAD") {
      writeResponse(res, 405, "Method Not Allowed", { Allow: "GET, HEAD" });
      return;
    }

    await serveStatic(req.method, url.pathname, req.headers.accept ?? "", res);
  } catch {
    writeResponse(res, 500, "Internal Server Error");
  }
});

server.listen(port, "0.0.0.0", () => {
  console.log(`Mars.Web test host listening on port ${port}.`);
});

function proxyToApi(req, res, url) {
  const transport = apiOrigin.protocol === "https:" ? httpsRequest : httpRequest;
  const headers = { ...req.headers };
  delete headers.connection;
  delete headers["proxy-connection"];
  delete headers["keep-alive"];
  delete headers.te;
  delete headers.trailer;
  delete headers.transferEncoding;
  delete headers.upgrade;
  headers.host = apiOrigin.host;
  headers["x-forwarded-proto"] = "http";
  headers["x-forwarded-host"] = req.headers.host ?? "";

  const upstream = transport(
    {
      protocol: apiOrigin.protocol,
      hostname: apiOrigin.hostname,
      port: apiOrigin.port,
      method: req.method,
      path: `${url.pathname}${url.search}`,
      headers
    },
    (upstreamResponse) => {
      const responseHeaders = { ...upstreamResponse.headers, ...securityHeaders };
      delete responseHeaders.connection;
      delete responseHeaders["transfer-encoding"];
      res.writeHead(upstreamResponse.statusCode ?? 502, responseHeaders);
      upstreamResponse.pipe(res);
    });

  upstream.on("error", () => {
    if (!res.headersSent) {
      writeResponse(res, 502, "Bad Gateway");
    } else {
      res.destroy();
    }
  });

  req.pipe(upstream);
}

async function serveStatic(method, requestPath, accept, res) {
  const decoded = decodeURIComponent(requestPath);
  const relative = decoded.replace(/^\/+/, "");
  let candidate = resolve(root, relative || "index.html");

  if (candidate !== root && !candidate.startsWith(`${root}${sep}`)) {
    writeResponse(res, 403, "Forbidden");
    return;
  }

  let info = await tryStat(candidate);
  if (info?.isDirectory()) {
    candidate = resolve(candidate, "index.html");
    info = await tryStat(candidate);
  }

  if (!info?.isFile() && accept.includes("text/html")) {
    candidate = resolve(root, "index.html");
    info = await tryStat(candidate);
  }

  if (!info?.isFile()) {
    writeResponse(res, 404, "Not Found");
    return;
  }

  const headers = {
    ...securityHeaders,
    "Content-Type": contentType(candidate),
    "Content-Length": String(info.size),
    "Cache-Control": candidate.includes(`${sep}assets${sep}`)
      ? "public, max-age=31536000, immutable"
      : "no-cache"
  };

  res.writeHead(200, headers);
  if (method === "HEAD") {
    res.end();
    return;
  }

  createReadStream(candidate).pipe(res);
}

async function tryStat(path) {
  try {
    return await stat(path);
  } catch {
    return null;
  }
}

function contentType(path) {
  return ({
    ".css": "text/css; charset=utf-8",
    ".html": "text/html; charset=utf-8",
    ".ico": "image/x-icon",
    ".js": "text/javascript; charset=utf-8",
    ".json": "application/json; charset=utf-8",
    ".map": "application/json; charset=utf-8",
    ".png": "image/png",
    ".svg": "image/svg+xml",
    ".txt": "text/plain; charset=utf-8",
    ".webp": "image/webp"
  })[extname(path).toLowerCase()] ?? "application/octet-stream";
}

function writeResponse(res, status, message, extraHeaders = {}) {
  const body = `${message}\n`;
  res.writeHead(status, {
    ...securityHeaders,
    ...extraHeaders,
    "Content-Type": "text/plain; charset=utf-8",
    "Content-Length": Buffer.byteLength(body)
  });
  res.end(body);
}

# syntax=docker/dockerfile:1.7

FROM node:24.21.0-bookworm-slim AS build
WORKDIR /web

COPY src/Mars.Web/package.json src/Mars.Web/package-lock.json ./
RUN npm ci

COPY src/Mars.Web ./
RUN npm run build

FROM node:24.21.0-bookworm-slim AS runtime
WORKDIR /app

ENV NODE_ENV=production \
    MARS_WEB_PORT=8080 \
    MARS_API_ORIGIN=http://api:8080

COPY --chown=node:node deploy/test/web-server.mjs ./server.mjs
COPY --from=build --chown=node:node /web/dist ./dist

USER node

EXPOSE 8080

CMD ["node", "server.mjs"]

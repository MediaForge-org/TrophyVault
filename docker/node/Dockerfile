# Development image for the Vite dev server.
# Pre-creates /app/node_modules with the host user's ids so the named volume mounted there is writable.
FROM node:22-alpine

ARG UID=1000
ARG GID=1000
RUN mkdir -p /app/node_modules && chown -R ${UID}:${GID} /app

WORKDIR /app
USER ${UID}:${GID}

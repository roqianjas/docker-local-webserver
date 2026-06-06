# ============================================================
# Node.js 22 LTS Dockerfile
# Includes: npm, yarn, pnpm, pm2
# ============================================================

FROM node:22-alpine

LABEL maintainer="Docker Local Webserver"
LABEL description="Node.js 22 LTS with yarn, pnpm, pm2 for web development"

# ============================================================
# System Dependencies
# ============================================================
RUN apk add --no-cache \
    git \
    curl \
    bash \
    # Python & build tools for native modules (node-gyp)
    python3 \
    make \
    g++

# ============================================================
# Global Node.js Packages
# ============================================================
RUN corepack enable

RUN npm install -g \
    pm2 \
    # Useful dev tools
    nodemon \
    ts-node \
    typescript

# ============================================================
# Cleanup
# ============================================================
RUN apk del make g++ \
    && rm -rf /var/cache/apk/* /tmp/* /root/.npm/_cacache

WORKDIR /var/www/html

# Keep container running (idle mode — exec into it to run projects)
CMD ["tail", "-f", "/dev/null"]

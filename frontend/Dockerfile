# Development stage
FROM node:20-alpine as development

WORKDIR /app

# Install necessary dependencies for native bindings
RUN apk add --no-cache python3 make g++

COPY package*.json ./
# Use npm install instead of npm ci to handle lock file issues
RUN npm install --frozen-lockfile || npm install

COPY . .

EXPOSE 3000

CMD ["npm", "run", "dev"]

# Production stage
FROM node:20-alpine as production

WORKDIR /app

# Install necessary dependencies for native bindings
RUN apk add --no-cache python3 make g++

COPY package*.json ./
# Use npm install instead of npm ci to handle lock file issues
RUN npm install --frozen-lockfile || npm install

COPY . .

# Set production environment variables before build
ENV NODE_ENV=production
RUN npm run build:prod

# Clean up dev dependencies after build
RUN npm install --only=production && npm cache clean --force

EXPOSE 3000

CMD ["node", ".output/server/index.mjs"]
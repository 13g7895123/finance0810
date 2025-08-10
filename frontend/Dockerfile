# Development stage
FROM node:20-alpine as development

WORKDIR /app

# Install necessary dependencies for native bindings
RUN apk add --no-cache python3 make g++

COPY package*.json ./
RUN npm ci

COPY . .

EXPOSE 3000

CMD ["npm", "run", "dev"]

# Production stage
FROM node:20-alpine as production

WORKDIR /app

# Install necessary dependencies for native bindings
RUN apk add --no-cache python3 make g++

COPY package*.json ./
RUN npm ci

COPY . .

RUN npm run build

# Clean up dev dependencies after build
RUN npm ci --only=production && npm cache clean --force

EXPOSE 3000

CMD ["node", ".output/server/index.mjs"]
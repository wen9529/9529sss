import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'dist',
  },
  // 开发环境代理 (本地调试用)
  server: {
    proxy: {
      '/api': {
        target: 'https://wenge9529.serv00.net',
        changeOrigin: true,
      }
    }
  }
})
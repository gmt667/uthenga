import { defineConfig } from 'vite'; import react from '@vitejs/plugin-react'; import tailwindcss from '@tailwindcss/vite';
export default defineConfig({
  base: process.env.VITE_BASE || '/',
  plugins:[react(),tailwindcss()],
  server:{port:5173,proxy:{'/api':{target:'http://127.0.0.1/uthenga',changeOrigin:true},'/ai':{target:'http://127.0.0.1:8001',changeOrigin:true}}},
  build:{outDir:'dist',emptyOutDir:true,manifest:true}
});

import { defineConfig } from 'vite'
import path from 'path'
import fs from 'fs'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

function rewritePublicPaths() {
  let base = '/'
  return {
    name: 'rewrite-public-paths',
    configResolved(config: any) {
      base = config.base || '/'
    },
    transformIndexHtml(html: string) {
      return html
        .replaceAll('href="/icons/', `href="${base}icons/`)
        .replace('href="/manifest.json"', `href="${base}manifest.json"`)
    },
    writeBundle() {
      const manifestPath = path.resolve(__dirname, 'dist/manifest.json')
      if (fs.existsSync(manifestPath)) {
        let manifest = fs.readFileSync(manifestPath, 'utf-8')
        const parsed = JSON.parse(manifest)
        parsed.start_url = base
        parsed.id = base
        parsed.scope = base
        parsed.icons = parsed.icons.map((icon: any) => ({
          ...icon,
          src: icon.src.replace(/^\//, base),
        }))
        fs.writeFileSync(manifestPath, JSON.stringify(parsed, null, 2))
      }
    },
  }
}

export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
    rewritePublicPaths(),
  ],
  base: process.env.VITE_BASE || '/',
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  },
  resolve: {
    alias: {
      // Alias @ to the src directory
      '@': path.resolve(__dirname, './src'),
    },
  },

  server: {
    proxy: {
      '/backend/api': {
        target: 'http://localhost',
        changeOrigin: true,
      },
    },
  },

  // File types to support raw imports. Never add .css, .tsx, or .ts files to this.
  assetsInclude: ['**/*.svg', '**/*.csv'],
})

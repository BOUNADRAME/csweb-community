const withNextra = require('nextra')({
  theme: 'nextra-theme-docs',
  themeConfig: './theme.config.jsx',
  defaultShowCopyCode: true,
  flexsearch: {
    codeblocks: true
  },
  latex: true
})

const isProduction = process.env.NODE_ENV === 'production'
const assetPrefix = isProduction ? '/csweb-community' : ''

module.exports = withNextra({
  // GitHub Pages configuration
  basePath: isProduction ? '/csweb-community' : '',
  assetPrefix: assetPrefix,
  output: 'export',

  // Disable image optimization for static export
  images: {
    unoptimized: true
  },

  // i18n configuration (disabled for static export)
  // Note: i18n routing is not compatible with output: 'export'
  // For multi-language support with static export, use sub-paths manually
  // i18n: {
  //   locales: ['fr', 'en'],
  //   defaultLocale: 'fr',
  // },

  // Optimizations
  reactStrictMode: true,
  swcMinify: true,

  // Trailing slash for better GitHub Pages compatibility
  trailingSlash: true,
})

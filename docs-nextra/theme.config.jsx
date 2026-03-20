import { useRouter } from 'next/router'
import { VersionSwitcher } from './components'

const logo = (
 <span style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
 <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
 <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z"/>
 </svg>
 <strong>CSWeb Community Platform</strong>
 </span>
)

const config = {
 logo,
 navbar: {
  extraContent: <VersionSwitcher />
 },
 project: {
 link: 'https://github.com/BOUNADRAME/csweb-community',
 },
 chat: {
 link: 'https://discord.gg/csweb-community', // À mettre à jour
 },
 docsRepositoryBase: 'https://github.com/BOUNADRAME/csweb-community/tree/master/docs-nextra',
 useNextSeoProps() {
 const { asPath } = useRouter()
 if (asPath !== '/') {
 return {
 titleTemplate: '%s – CSWeb Community'
 }
 }
 },
 head: (
 <>
 <meta name="viewport" content="width=device-width, initial-scale=1.0" />
 <meta property="og:title" content="CSWeb Community Platform" />
 <meta property="og:description" content="Documentation officielle de CSWeb Community Platform - Architecture flexible, Multi-DB, Docker" />
 <link rel="icon" href="/favicon.ico" />
 </>
 ),
 primaryHue: 212, // Blue
 darkMode: true,
 footer: {
 text: (
 <div style={{ display: 'flex', flexDirection: 'column', gap: '8px', alignItems: 'center' }}>
 <span>
 Made with by{' '}
 <a
 href="https://bounadrame.github.io/portfolio/"
 target="_blank"
 rel="noopener noreferrer"
 style={{ fontWeight: 'bold', textDecoration: 'underline' }}
 >
 Bouna DRAME
 </a>
 {' '}© 2026
 </span>
 <span style={{ fontSize: '0.875rem', opacity: 0.7 }}>
 Full-Stack Developer | Open Source Contributor | CSPro & Statistical Systems Expert
 </span>
 <div style={{ display: 'flex', gap: '12px', marginTop: '4px' }}>
 <a href="https://bounadrame.github.io/portfolio/" target="_blank" rel="noopener noreferrer" style={{ opacity: 0.8, transition: 'opacity 0.2s' }}>
 Portfolio
 </a>
 <a href="https://github.com/BOUNADRAME" target="_blank" rel="noopener noreferrer" style={{ opacity: 0.8, transition: 'opacity 0.2s' }}>
 GitHub
 </a>
 <a href="https://www.linkedin.com/in/bouna-drame" target="_blank" rel="noopener noreferrer" style={{ opacity: 0.8, transition: 'opacity 0.2s' }}>
 LinkedIn
 </a>
 </div>
 </div>
 ),
 },
 toc: {
 backToTop: true,
 float: true,
 title: 'Sur cette page'
 },
 sidebar: {
 defaultMenuCollapseLevel: 1,
 toggleButton: true
 },
 navigation: {
 prev: true,
 next: true
 },
 editLink: {
 text: 'Modifier cette page sur GitHub '
 },
 feedback: {
 content: 'Une question ? Donnez votre avis ',
 labels: 'feedback'
 },
 gitTimestamp: true,
 search: {
 placeholder: 'Rechercher dans la documentation...'
 }
}

export default config

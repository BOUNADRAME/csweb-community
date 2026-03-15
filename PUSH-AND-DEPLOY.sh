#!/bin/bash

# Script de Push et Déploiement - CSWeb Nextra Documentation
# Auteur: Bouna DRAME
# Date: 15 Mars 2026

echo "=========================================="
echo "🚀 CSWeb Nextra Documentation"
echo "    Push & Deploy to GitHub Pages"
echo "=========================================="
echo ""

# Couleurs
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Vérifier qu'on est sur master
CURRENT_BRANCH=$(git branch --show-current)
if [ "$CURRENT_BRANCH" != "master" ]; then
    echo -e "${RED}❌ Erreur: Vous devez être sur la branche 'master'${NC}"
    echo "   Branche actuelle: $CURRENT_BRANCH"
    exit 1
fi

echo -e "${GREEN}✅ Branche: $CURRENT_BRANCH${NC}"
echo ""

# Vérifier qu'il y a un commit récent
LAST_COMMIT=$(git log -1 --oneline)
echo -e "${BLUE}📝 Dernier commit:${NC}"
echo "   $LAST_COMMIT"
echo ""

# Vérifier le statut git
if [[ $(git status --porcelain) ]]; then
    echo -e "${YELLOW}⚠️  Il y a des fichiers non commités${NC}"
    git status --short
    echo ""
    read -p "Voulez-vous continuer quand même? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Vérifier la connexion GitHub
echo -e "${BLUE}🔗 Vérification connexion GitHub...${NC}"
if ! git ls-remote --exit-code origin &> /dev/null; then
    echo -e "${RED}❌ Impossible de se connecter à GitHub${NC}"
    echo "   Vérifiez votre connexion internet et vos credentials GitHub"
    exit 1
fi

echo -e "${GREEN}✅ Connexion GitHub OK${NC}"
echo ""

# Push vers master
echo -e "${BLUE}📤 Push vers origin/master...${NC}"
if git push origin master; then
    echo -e "${GREEN}✅ Push réussi!${NC}"
else
    echo -e "${RED}❌ Erreur lors du push${NC}"
    exit 1
fi

echo ""
echo "=========================================="
echo -e "${GREEN}✅ PUSH TERMINÉ AVEC SUCCÈS!${NC}"
echo "=========================================="
echo ""

# Informations post-push
echo -e "${BLUE}📊 Prochaines étapes:${NC}"
echo ""
echo "1. Vérifier GitHub Actions (2-3 minutes)"
echo -e "   ${YELLOW}https://github.com/BOUNADRAME/pg_csweb8_latest_2026/actions${NC}"
echo ""
echo "2. Attendre le déploiement"
echo "   - Build: ~2 minutes"
echo "   - Deploy: ~30 secondes"
echo ""
echo "3. Vérifier le site déployé"
echo -e "   ${YELLOW}https://BOUNADRAME.github.io/pg_csweb8_latest_2026/${NC}"
echo ""

# Proposer d'ouvrir GitHub Actions
read -p "Ouvrir GitHub Actions dans le navigateur? (y/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    if command -v open &> /dev/null; then
        open "https://github.com/BOUNADRAME/pg_csweb8_latest_2026/actions"
    elif command -v xdg-open &> /dev/null; then
        xdg-open "https://github.com/BOUNADRAME/pg_csweb8_latest_2026/actions"
    else
        echo "Ouvrez manuellement: https://github.com/BOUNADRAME/pg_csweb8_latest_2026/actions"
    fi
fi

echo ""
echo -e "${GREEN}🎉 Documentation Nextra déployée avec succès!${NC}"
echo ""
echo -e "${BLUE}Made with ❤️  by Bouna DRAME${NC}"
echo -e "${YELLOW}🌐 Portfolio: https://bounadrame.github.io/portfolio/${NC}"
echo ""

# Contribuer à Noliae Mail OSS

Merci de t'intéresser au projet ! Voici comment contribuer efficacement.

## Workflow

1. **Fork** le repo
2. Crée une branche depuis `main` : `git checkout -b fix/ma-correction`
3. Code, teste, commit
4. Pousse sur ton fork
5. Ouvre une **Pull Request** vers `main`

## Style de code

### PHP
- **PSR-12** strict (vendor/bin/pint --test passe en CI)
- Type-hints partout (PHP 8.4)
- Pas de `mixed` sauf justification dans un commentaire

### Vue 3 / JS
- `<script setup>` Composition API
- Tailwind utility-first (pas de fichiers .css custom sauf rare)
- Pas de jQuery, pas d'Axios manuel (utilise Inertia router)

### Commits
- Style impératif court en français OU anglais (consistance par PR)
- Référence l'issue si applicable : `Fix #42 — corrige le snooze`

## Tests

```bash
composer install
npm ci && npm run build
vendor/bin/phpunit
```

PR sans test cassé → CI rouge → merge bloqué.

## Ce qu'on accepte

✅ Bug fixes, optimisations, traductions
✅ Nouvelles fonctions mail (filtres, signatures, etc.)
✅ Améliorations UX (raccourcis, accessibilité, dark mode tweaks)
✅ Support de providers de stockage S3 alternatifs
✅ Tests unitaires et d'intégration

## Ce qu'on n'acceptera probablement pas

❌ Intégration IA générative côté serveur (le projet est volontairement sans IA)
❌ Trackers, analytics tiers
❌ Dépendances cloud propriétaires obligatoires
❌ Code mort, complexité inutile

## Licence

En soumettant une PR, tu acceptes que ta contribution soit publiée sous **AGPL-3.0** (la licence du projet).

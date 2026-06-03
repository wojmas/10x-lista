---
starter_id: laravel
package_manager: composer
project_name: lista-zakupow
hints:
  language_family: php
  team_size: solo
  deployment_target: render
  ci_provider: github-actions
  ci_default_flow: auto-deploy-on-merge
  bootstrapper_confidence: verified
  path_taken: standard
  quality_override: false
  self_check_answers: null
  has_auth: true
  has_payments: false
  has_realtime: false
  has_ai: false
  has_background_jobs: false
---

## Why this stack

Solo developer building a family shopping list web-app with auth in PHP under a 3-week after-hours timeline. Laravel is the recommended default for `(web-app, php)` and ships batteries-included: built-in auth scaffolding covers FR-001, Eloquent ORM handles the product/shop/category data model, and Blade templates deliver the responsive UI the PRD requires. Scale is trivial (3-5 family members), deployment targets Render, and CI runs on GitHub Actions with auto-deploy on merge. Bootstrapper confidence is verified — scaffolding will be smooth.

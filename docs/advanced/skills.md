---
title: "AI Assistant Skills"
description: "Install the oilab-laravel-drive skill in a host application"
section: advanced
order: 3
---

# AI Assistant Skills

The package ships an AI skill describing its models, services, MCP tools and rules.

```bash
php artisan oi:skills oilab-laravel-drive --project
```

It is synced from `resources/stubs/ai-skill.md` into `.claude/skills/` and `.junie/skills/` on every `composer install`.

# Changelog

All notable changes to `oi-lab/oi-laravel-drive` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-16

### Added

- Drives belonging to any entity (`driveable` morph), single or multiple per entity
- Folders and files built on oi-laravel-attachments, a file filed in several folders, root files
- Owner and settable date of creation for every folder and file (`drive_entries`)
- Trash with restore, folder deletion with or without its exclusive files, `drive:purge-trash`
- Optional unique folder names per parent folder
- `DriveManager`, `DriveFolders`, `DriveFiles`, `DriveUploads` services and laravel-data DTOs
- JSON API (can be switched off), temporary download links, signed upload links
- 13 MCP tools for laravel/mcp ^0.9|^1.0, kebab or snake case names
- 17 events for indexing and auditing
- `DriveableResolver` contract and replaceable `DrivePolicy`
- PHP 8.2+, Laravel 11/12/13 — 44 Pest tests

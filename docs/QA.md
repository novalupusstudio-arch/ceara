# QA Checklist

## General

- App loads without console errors.
- No broken links or missing assets.
- UI works on desktop and mobile widths.
- Text does not overflow buttons, cards, tables, or panels.
- Empty, loading, success, and error states are handled where relevant.

## XAMPP / PHP

- PHP files pass syntax checks before sync.
- Local XAMPP path is up to date.
- Local URL loads the same files as the workspace.
- Server-side errors are visible during development and not leaked in production.

## Forms

- Required fields are validated.
- Invalid input shows useful feedback.
- Successful submissions confirm completion.
- Duplicate submissions are prevented where needed.

## Database

- Schema changes are documented.
- Seed/test data is separated from production data.
- Queries handle missing or invalid records.

## Release Check

- `git status` is clean or intentionally dirty.
- Important changes are committed.
- README and spec docs match the current behavior.


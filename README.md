# ShikhiAI

AI-assisted learning roadmaps for students in Bangladesh. Built for Ostad Presents YUNet AIgnite Buildathon 2026.

[Live app](https://shikhiai.baharsoftbd.com/) | [Demo video](https://youtu.be/tk5xNOL1IdM)

## Features

- Four-week roadmaps personalized by goal, experience, weekly hours, and learning challenges.
- Five learning tracks: Web development, Data analysis, English communication, UI/UX design, and AI & prompt engineering.
- Groq-powered roadmap generation and milestone translation, with clearly labeled fallback study plans.
- Saved plans, milestone checklists, and overall progress in browser storage.
- Plan download, export, and import.
- English, Bangla, Arabic, Dutch, and German interfaces.

## Stack

PHP backend with cURL and mbstring; HTML, CSS, and vanilla JavaScript frontend. The backend calls the Groq Chat Completions API using `openai/gpt-oss-120b`. No database is required for learner plans.

## Setup

This repository is a snapshot of the hosted application. PHP 8+ with cURL and mbstring is required.

1. Place the application files in a PHP-enabled web root.
2. Set `GROQ_API_KEY` in the server environment. The app does not automatically load `.env` files.
3. In `api.php`, update the allowed origin (`https://shikhiai.baharsoftbd.com`) to your deployment origin.
4. Update the request-budget path (`/home/baharsof/shikhi-ai-request-budget.json`) to a writable file outside your public web root. The backend fails closed if it cannot lock this file.
5. If using a private PHP configuration file instead of the environment variable, update `$configPath` in `api.php` to a file outside the web root that returns an array containing `GROQ_API_KEY`. Never commit this private file.
6. Open the app through your PHP server. `GET /api.php` reports whether the AI key is configured, without returning the key.

For local development, set the origin and private paths above, then run `php -S localhost:8000` from this directory and visit `http://localhost:8000`.

## Privacy and limitations

Names, roadmaps, and checkmarks remain in browser storage. Roadmap generation sends the selected goal, level, weekly hours, challenge, and language through the PHP server to Groq. Translation sends milestone text and the selected language. Avoid sensitive personal information. The API uses hashed-IP request-budget counters outside the public root; it does not store learner plans in a server database.

Roadmaps are study suggestions, not guarantees of jobs, admissions, or skill mastery. Progress measures checked milestones. Browser data can be lost when storage is cleared; export important plans.

## Repository contents

- `index.php`: application interface and browser-side logic.
- `api.php`: validation, request budgets, and Groq integration.
- `favicon.svg`, `aignite-logo-*.png`, `official-sticker.svg`: interface and event artwork.

Private server configuration, API credentials, runtime request counters, and hosting-specific PHP settings are excluded. Event branding belongs to its respective owners; no license to third-party artwork is granted by this repository.


<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://zethal.mevasearch.com/img/logo-dark.png?v=1">
    <img src="https://zethal.mevasearch.com/img/logo.png" width="72" height="72" alt="Zethal Framework logo">
  </picture>
</p>

<h1 align="center">Zethal Framework</h1>

<p align="center">
  The original framework MevaSearch was built on.<br>
  A self-hosted private search template in plain PHP.
</p>

<p align="center">
  <a href="https://zethal.wbsrv.icu/">Live demo</a> ·
  <a href="https://zethal.mevasearch.com/">Website</a> ·
  <a href="https://zethal.mevasearch.com/docs.html">Documentation</a> ·
  <a href="https://weborbiton.com/#pricing">Pricing</a>
</p>

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://zethal.mevasearch.com/screens/home-dark.webp?v=1">
    <img src="https://zethal.mevasearch.com/screens/home-light.webp" alt="Zethal Framework home page" width="860">
  </picture>
</p>

## About

Zethal Framework is the foundation MevaSearch started from, released as a clean template for your own search engine. Upload it to any PHP host, give it your name and it works right away.

No API keys, no database, no build step and no trackers.

This public edition does not include the MevaSearch index, ranking, data or services.

## Features

- **Web, images, videos and news** with paging and a knowledge panel, powered by Wikipedia and Wikimedia Commons until you connect your own provider.
- **Bring your own search provider** through one environment variable and a small JSON format.
- **AI summaries** with Anthropic or any OpenAI-compatible API, plus a built-in extractive summary that needs no model.
- **Search with a photo**: images are resized in the browser and never stored.
- **!bangs** with live suggestions, easy to extend in `bangs.php`.
- **Private by default**: tracking parameters stripped from links, hidden domains, settings stored only in the browser, strict Content Security Policy.
- **Languages as folders**: copy `lang/en`, translate it, add `info.json` and it appears in settings.
- **Settings page** with theme, page capsule, compact results, shortcuts, SafeSearch and data reset.
- **Light and dark mode**, responsive down to small phones.

## Requirements

- PHP 8.1 or newer
- `curl` and `mbstring` extensions
- Outbound HTTPS to `wikipedia.org` and `wikimedia.org`, or to your own provider

## Quick start

```bash
git clone https://github.com/mevasearch/ZethalFramework
cd ZethalFramework
php -S 127.0.0.1:8080
```

Open `http://127.0.0.1:8080` and search for anything.

To make it yours, change `app_name`, `app_tagline` and `app_description` in `lang/en/common.php`.

## Configuration

Everything is optional. Settings are environment variables read on the server, and keys never reach the browser.

| Variable | Purpose |
| --- | --- |
| `ZETHAL_SEARCH_URL` | Your search endpoint. Replaces Wikipedia and Commons for every tab. |
| `ZETHAL_SEARCH_KEY` | Sent to your endpoint as a Bearer token. |
| `ZETHAL_AI_PROVIDER` | `anthropic` (default) or any other value for an OpenAI-compatible API. |
| `ZETHAL_AI_URL` | Chat completions URL for OpenAI-compatible providers. |
| `ZETHAL_AI_KEY` | API key of your AI provider. |
| `ZETHAL_AI_MODEL` | Model name. AI is used only when both key and model are set. |

On Apache or LiteSpeed:

```apache
SetEnv ZETHAL_AI_KEY your-key
SetEnv ZETHAL_AI_MODEL your-model
```

Before going public, set a contact in `ZETHAL_USER_AGENT` at the top of `api.php`. Wikimedia asks every client to identify itself.

The full guide covers the provider JSON format, languages, bangs and deployment: **[zethal.mevasearch.com/docs.html](https://zethal.mevasearch.com/docs.html)**

## License

Zethal Framework is released under the **SA-SDL** (Source-Available Software Distribution License). See [`LICENSE`](LICENSE).

- Free to use, modify and self-host, including in projects that earn money.
- Offering Zethal itself as a standalone paid product or service above 1500 USD per month requires review by the Licensor.
- Attribution is not required, but a "Built with Zethal Framework" note is appreciated.

[License](https://legal.weborbiton.com/license) · [Commercial license](https://legal.weborbiton.com/commercial-license) · [Pricing](https://weborbiton.com/#pricing)

Source available is not the same as open source in the OSI sense. The license text is what counts.

## Credits

Built by the MevaSearch team at WebOrbiton.

Space Grotesk font by The Space Grotesk Project Authors, licensed under the SIL Open Font License 1.1 (`fonts/space-grotesk/OFL.txt`).

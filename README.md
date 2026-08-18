# Citations (Scopus / Crossref) — OJS 3.5 plugin

[![OJS](https://img.shields.io/badge/OJS-3.5-brightgreen)](https://pkp.sfu.ca/ojs/)
[![Version](https://img.shields.io/badge/version-3.5.0.0-blue)](version.xml)
[![License](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](LICENSE)

**⬇️ Install package:** [OJS 3.5](../../releases) — or browse all [Releases](../../releases).

Shows, on the article (and preprint) landing page, the **total number of citations** and the
**list of citing works**, gathered from **Crossref Cited-by**, **Scopus**, **Europe PMC** and
**Google Scholar**.

> **Original plugin by [Ronny Bölter](https://github.com/RBoelter)
> (`RBoelter/citations`)**, with contributions from
> **[Armin Günther](https://github.com/aguen)** and from the **[Lepidus](https://lepidus.com.br)**
> team (Jhon and Laís). The design, the Crossref/Scopus/Europe PMC integration, the templates,
> the CSS and the JavaScript are theirs. This branch only carries that work forward to **OJS
> 3.5**, which upstream does not target yet — maintained by **[OJSBR](https://ojsbr.com)**.
> New features belong upstream first.
> See [Credits & acknowledgements](#credits--acknowledgements).

## Compatibility & branches

| OJS version | Branch | Plugin release |
|-------------|--------|----------------|
| OJS/OPS 3.5.x | [`stable-3_5_0`](../../tree/stable-3_5_0) *(default)* | 3.5.0.0 |
| OJS/OPS 3.4.x | [`stable-3_4_0`](../../tree/stable-3_4_0) *(upstream mirror)* | 3.4.0.1 |

Requires PHP 8.2+. Tested on OJS 3.5.0-3 and 3.5.0-5.

## What it does

* Adds a block to the article page with a **counter per source**: Crossref Cited-by, Scopus,
  Europe PMC, plus a link to a Google Scholar search for the DOI.
* Below the counters, lists the **citing works** — authors, year, title, journal, volume, issue,
  pages and a link to each citing DOI.
* Counts are fetched by the reader's browser from a plugin endpoint, so the article page itself
  is not held up waiting on Crossref or Elsevier.
* Deduplicates: a work returned by both Crossref and Scopus is listed once.

## What it does NOT do

* **It does not enable Crossref Cited-by for you.** The service is not on by default — you must
  [ask Crossref](https://www.crossref.org/contact) to enable it for your prefix. Until then every
  article legitimately reports zero.
* **Cited-by only returns citations to your own DOIs.** Querying a DOI from another member always
  comes back empty; that is Crossref's behaviour, not a bug here.
* **The list can be shorter than the counter.** Crossref returns every citing item, but the plugin
  renders only `journal_cite` and `book_cite` entries — conference papers and dissertations count
  toward the total without appearing in the list. This is upstream behaviour, kept as is.

## Requirements

* A **DOI registered on the article** — without it the block does not render at all.
* For Crossref: an active [membership](https://www.crossref.org/membership/) **and** the Cited-by
  service enabled for your prefix. The credentials are the same ones used for depositing.
* For Scopus: an [Elsevier API key](https://dev.elsevier.com/sc_apis.html) (free).
* Europe PMC and Google Scholar need no credentials.

## Installation

1. Upload the release package in **Settings → Website → Plugins → Upload a new plugin**, or copy
   the folder to `plugins/generic/citations` (do **not** rename the folder: OJS 3.5 derives the
   plugin class namespace from the directory name).
2. Enable **Scopus/Crossref Plugin** in the plugin list.
3. Open its **Settings** and fill in the credentials.

> **Where the block appears depends on the theme.** It attaches to the
> `Templates::Article::Details` hook (and `Templates::Preprint::Details` on OPS). A custom theme
> that never calls that hook will not show anything, and a theme that calls it outside its article
> card will render the block without the card's styling.

## Settings

| Field | What it does |
|-------|--------------|
| Source | Crossref, Scopus, or both |
| Scopus API key | Stored, never displayed again |
| Crossref user / password | The same credentials used for depositing |
| Total number of citations | Shows the counters |
| List of citing articles | Shows the list below the counters |
| Google Scholar | Adds a link to a Scholar search for the DOI |
| Europe PMC | Adds the Europe PMC counter and link |
| Block height | Maximum height in pixels; `0` or empty means unlimited |

## Endpoint

The counters are fetched by the browser from:

```
<host>/index.php/<journal>/citations/get?doi=<doi>
```

It answers a `JSONMessage`, so the payload lives under `content`.

## What changed for OJS 3.5

Upstream's newest branch is `stable-3_4_0`, and it does not load on OJS 3.5. This branch is that
branch plus the smallest set of changes needed to run:

**The three that actually broke it**

* `PKP\notification\PKPNotification` no longer exists (renamed to `PKP\notification\Notification`)
  — the settings form fataled on **save**.
* The `LoadHandler` hook no longer accepts `define('HANDLER_CLASS', …)`: `PKPPageRouter` in 3.5
  **throws** if that constant is defined. The handler is now injected through `$params[3]` of the
  hook's `[&$page, &$op, &$sourceFile, &$handler]` signature. Without this the `citations/get`
  endpoint is dead.
* The global `import()` function was removed in 3.5 — the leftover
  `import('lib.pkp.classes.linkAction.request.AjaxModal')` call fataled the plugin list page.

**The rest**

* Reads the DOI from `getCurrentPublication()->getDoi()` instead of `Submission::getStoredPubId()`,
  deprecated since 3.2 and only a proxy to the publication.
* Guards a null journal context in the template hook and in the handler.
* `CitationsHandler::loadSettings()` no longer passes an array or an empty string to
  `json_decode()` — under PHP 8 the first is a `TypeError` and the second returns `null`, both
  violating the method's `array` return type.
* Initialises `$result` in `CitationsHandler::get()` (undefined variable when no provider matched).
* Replaces a `Monolog\Logger` built with **no handlers** — every Guzzle failure was being
  swallowed silently — with `error_log()`.
* 3.5 house style: `[]` array syntax, first-class callables in `Hook::add()`, and
  `Hook::CONTINUE` / `Hook::ABORT` instead of bare booleans.
* Locales: added `pt_BR`, `fr` and `it` (PKP 3.5 resolves `fr`, not `fr_FR`), for seven in total.

The processors, templates, CSS and JavaScript are untouched upstream code.

## Credits & acknowledgements

* **[Ronny Bölter](https://github.com/RBoelter)** — author and maintainer of the original
  [`RBoelter/citations`](https://github.com/RBoelter/citations) plugin. The whole plugin is his
  design and his work.
* **[Armin Günther](https://github.com/aguen)** — contributor upstream.
* **[Lepidus Tecnologia](https://lepidus.com.br)** (Jhon and Laís) — contributors upstream.
* **[Crossref](https://www.crossref.org/services/cited-by/)**, **[Elsevier /
  Scopus](https://dev.elsevier.com/)**, **[Europe PMC](https://europepmc.org/)** and **Google
  Scholar** — the citation data and the logos are theirs; this plugin only queries and displays it.
* **[Public Knowledge Project](https://pkp.sfu.ca/)**, **Simon Fraser University** and
  **John Willinsky** — OJS/OPS and the plugin framework.
* **[OJSBR](https://ojsbr.com)** — this OJS 3.5 branch and its maintenance.

## License

GNU GPL v3, the same as upstream. See [LICENSE](LICENSE) and [docs/COPYING](docs/COPYING).

---

## 🇧🇷 Em português

Exibe, na página do artigo, o **número total de citações** e a **lista dos trabalhos que citam**,
obtidos no **Crossref Cited-by**, na **Scopus**, no **Europe PMC** e no **Google Acadêmico**.

> **Este plugin não é da OJSBR.** Ele foi criado e é mantido pelo
> **[Ronny Bölter](https://github.com/RBoelter)** (`RBoelter/citations`), com contribuições do
> **[Armin Günther](https://github.com/aguen)** e da equipe da **[Lepidus](https://lepidus.com.br)**
> (Jhon e Laís). O desenho, a integração com Crossref/Scopus/Europe PMC, os templates, o CSS e o
> JavaScript são deles. Esta branch apenas leva esse trabalho adiante para o **OJS 3.5**, versão
> que o projeto original ainda não cobre — manutenção da **[OJSBR](https://ojsbr.com)**.
> Funcionalidade nova deve ir primeiro para o repositório original.

### O que ele faz

* Um contador por fonte na página do artigo: Crossref Cited-by, Scopus, Europe PMC e um link para
  a busca no Google Acadêmico pelo DOI.
* Abaixo dos contadores, a lista dos trabalhos que citam — autores, ano, título, periódico, volume,
  número, páginas e o link para o DOI de cada um.
* Os números são buscados pelo navegador do leitor num endpoint do plugin, então a página do artigo
  não fica esperando a Crossref nem a Elsevier responderem.
* Um trabalho que aparece na Crossref e na Scopus é listado uma vez só.

### O que ele NÃO faz

* **Não habilita o Cited-by para você.** O serviço não vem ligado: é preciso
  [pedir à Crossref](https://www.crossref.org/contact) a habilitação para o seu prefixo. Enquanto
  isso não acontece, todo artigo devolve zero — e está certo.
* **O Cited-by só devolve citações aos DOIs do próprio membro.** Consultar o DOI de outra revista
  sempre volta vazio; é o comportamento da Crossref, não um defeito daqui.
* **A lista pode ser menor que o contador.** A Crossref devolve todos os itens citantes, mas o
  plugin monta a lista só com `journal_cite` e `book_cite` — anais e teses entram no total sem
  aparecer na lista. É comportamento do original, mantido como está.

### Requisitos

* **DOI cadastrado no artigo** — sem DOI o bloco nem aparece.
* Crossref: [associação](https://www.crossref.org/membership/) ativa **e** o Cited-by habilitado
  para o seu prefixo. A credencial é a mesma usada no depósito.
* Scopus: uma [chave de API da Elsevier](https://dev.elsevier.com/sc_apis.html) (gratuita).
* Europe PMC e Google Acadêmico não pedem credencial.

### Instalação

1. Envie o pacote em **Configurações → Website → Plugins → Enviar um novo plugin**, ou copie a
   pasta para `plugins/generic/citations` (**não** renomeie a pasta: o OJS 3.5 deriva o namespace
   da classe do nome do diretório).
2. Habilite o **Plugin Scopus/Crossref** na lista.
3. Abra as **Configurações** dele e preencha as credenciais.

> **Onde o bloco aparece depende do tema.** Ele se pendura no hook
> `Templates::Article::Details` (e no `Templates::Preprint::Details`, no OPS). Tema próprio que não
> chama esse hook não exibe nada; tema que chama fora do cartão do artigo exibe o bloco sem o
> estilo do cartão.

### O que mudou para o OJS 3.5

A branch mais nova do original é a `stable-3_4_0` e ela **não carrega** no OJS 3.5. As três quebras
reais foram:

1. `PKPNotification` deixou de existir no 3.5 (virou `Notification`) — o formulário de configuração
   dava fatal ao **salvar**.
2. O `PKPPageRouter` do 3.5 **lança exceção** se a constante `HANDLER_CLASS` estiver definida; o
   handler agora entra pelo `$params[3]` do hook `LoadHandler`. Sem isso o endpoint `citations/get`
   fica morto.
3. A função global `import()` foi removida no 3.5 — a chamada remanescente derrubava a página de
   plugins.

As demais mudanças (DOI lido da publicação, guardas de contexto nulo, `json_decode()` que era
`TypeError` no PHP 8, logger sem handler que engolia todo erro do Guzzle, estilo do 3.5 e os
locales novos) estão detalhadas na seção em inglês acima. Os processadores, os templates, o CSS e
o JavaScript são código original, intocado.

### Idiomas

`de`, `en`, `es`, `fr`, `it`, `pt`, `pt_BR`.

### Licença

GNU GPL v3, a mesma do original. Veja o [LICENSE](LICENSE) e o [docs/COPYING](docs/COPYING).

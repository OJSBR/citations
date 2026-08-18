# Contributing

Thanks for your interest in improving this plugin!

**Please open features and bug reports upstream first.** This repository is an OJSBR
fork of [`RBoelter/citations`](https://github.com/RBoelter/citations) by
**[Ronny Bölter](https://github.com/RBoelter)**, and it exists for one reason: to keep
the plugin running on OJS 3.5, which upstream does not target yet. Anything that is not
specific to the 3.5 port belongs in the original project, where the whole community
benefits from it.

Bring it here when it is about the OJS 3.5 branch: a 3.5 API that moved, a regression
introduced by the port, or a translation. Released under the **GNU GPL v3**, like
upstream.

## Reporting issues

- Open an issue describing the problem or suggestion.
- Include your **OJS version**, the **plugin version** (see `version.xml`), and steps
  to reproduce. Errors from `error_log` / the browser console help a lot.

## Branch model

Each supported PKP version lives in its own branch, following the PKP convention:

| Branch | Target |
|--------|--------|
| `stable-3_5_0` | OJS 3.5.x |
| `stable-3_4_0` | OJS 3.4.x *(upstream mirror — send changes to the original repo)* |

**Always base your work on — and open your pull request against — the branch that matches
the PKP version you are targeting.**

## Pull requests

1. Fork the repository and create a topic branch from the relevant `stable-*` branch.
2. Keep the repository layout intact: the repo root **is** the plugin folder (so
   `version.xml` stays at the root).
3. Follow the existing code style and the
   [PKP coding conventions](https://docs.pkp.sfu.ca/dev/documentation/en/coding) —
   namespaced classes (`APP\plugins\...`, PSR-4), hooks via `PKP\plugins\Hook`, etc.
4. Add/keep translation strings in `locale/<lang>/locale.po` (the plugin ships `de`, `en`, `es`, `fr`, `it`, `pt` and `pt_BR`; keep `en` and `pt_BR` in sync).
5. When your change is user-visible, bump `<release>` and `<date>` in `version.xml`.
6. Describe **what** and **why** in the PR, and mention which PKP version you tested on.

By submitting a contribution you agree to license it under the **GNU GPL v3**, consistent
with this project.

---

## 🇧🇷 Português

Obrigado pelo interesse em melhorar este plugin!

**Abra melhorias e relatos de erro primeiro no repositório original.** Este aqui é um
fork da OJSBR do [`RBoelter/citations`](https://github.com/RBoelter/citations), do
**[Ronny Bölter](https://github.com/RBoelter)**, e existe por um motivo só: manter o
plugin funcionando no OJS 3.5, versão que o original ainda não cobre. O que não for
específico do port para o 3.5 pertence ao projeto original, onde a comunidade inteira
se beneficia.

Traga para cá o que for da branch 3.5: uma API do 3.5 que mudou de lugar, uma regressão
causada pelo port, ou uma tradução. Distribuído sob a **GNU GPL v3**, como o original.

### Relatando problemas

- Abra uma *issue* descrevendo o problema ou a sugestão.
- Informe a **versão do OJS**, a **versão do plugin** (veja `version.xml`) e o passo a
  passo para reproduzir. Mensagens do `error_log` / console do navegador ajudam muito.

### Modelo de branches

Cada versão suportada do PKP fica em sua própria branch, seguindo a convenção da PKP:
`stable-3_5_0` (OJS 3.5.x) e `stable-3_4_0` (OJS 3.4.x, espelho do original). **Baseie seu trabalho — e abra o pull request — na branch que
corresponde à versão do PKP que você está mirando.**

### Pull requests

1. Faça um *fork* e crie uma branch de trabalho a partir da `stable-*` correspondente.
2. Mantenha o layout do repositório: a raiz do repo **é** a pasta do plugin (o `version.xml`
   fica na raiz).
3. Siga o estilo do código e as
   [convenções de código da PKP](https://docs.pkp.sfu.ca/dev/documentation/en/coding) —
   classes com namespace (`APP\plugins\...`, PSR-4), hooks via `PKP\plugins\Hook` etc.
4. Mantenha as strings de tradução em `locale/<idioma>/locale.po` (o plugin traz `de`, `en`, `es`, `fr`, `it`, `pt` e `pt_BR`; mantenha `en` e `pt_BR` em dia).
5. Em mudanças visíveis ao usuário, incremente `<release>` e `<date>` no `version.xml`.
6. Explique **o quê** e **por quê** no PR, e diga em qual versão do PKP testou.

Ao enviar uma contribuição, você concorda em licenciá-la sob a **GNU GPL v3**, coerente com
este projeto.

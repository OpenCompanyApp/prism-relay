# Prism Relay TODO

`prism-relay` now has catalog parity with Hermes and Opencode. The remaining gap
is runtime parity: a small set of providers are present in the generated
registry, but are still metadata-only because Prism does not yet have a
compatible execution adapter for them in this package.

This document exists to capture:

- why each provider is still metadata-only
- what package-level changes are needed
- where the work should land in `prism-relay`

## Current architecture context

The runtime path in `prism-relay` is now split into three layers:

- `scripts/sync-registry.mjs`
  - generates provider/model metadata from Hermes and Opencode
- `src/Registry/RelayRegistry.php`
  - resolves canonical providers, aliases, and model aliases
- `src/RelayManager.php`
  - maps provider entries to Prism runtime drivers or custom relay providers

That means catalog completeness is no longer the hard part. The remaining work
is adapter work inside `RelayManager` plus new provider classes under
`src/Providers/` where Prism does not already have a usable transport.

## What "metadata-only" means

A metadata-only provider can currently do all of this:

- appear in the generated registry
- expose models, aliases, defaults, and pricing metadata
- participate in normalization and accounting metadata lookups

But it cannot yet do this:

- be resolved by `RelayManager` into a working Prism `Provider`
- execute text / structured / stream requests from Iris or other Prism consumers

In practice, these providers currently resolve to
`src/Providers/UnsupportedTransportProvider.php`.

## Remaining runtime adapters

### `cerebras`

Why it is blocked:
- Opencode uses `@ai-sdk/cerebras`
- Prism does not ship a Cerebras provider
- This is not just an OpenAI-compatible base URL swap; the SDK-specific request
  contract and headers need to be matched

What needs to change:
- Add a dedicated relay provider class under `src/Providers/`
- Add a driver mapping in `src/RelayManager.php`
- Preserve the `X-Cerebras-3rd-Party-Integration` header behavior Opencode sets

Likely implementation shape:
- `src/Providers/Cerebras.php`
- openai-like request/response mapping with Cerebras-specific headers

### `cloudflare-ai-gateway`

Why it is blocked:
- Opencode routes this through Cloudflare's unified gateway abstraction
- Model IDs are provider-qualified and then wrapped in the gateway runtime
- Credentials are not a normal single API key; they require account ID, gateway
  ID, and API token

What needs to change:
- Add a dedicated relay provider class under `src/Providers/`
- Add gateway credential/config support in `RelayManager`
- Preserve Cloudflare-specific metadata / cache options where they matter

Likely implementation shape:
- `src/Providers/CloudflareAiGateway.php`
- a provider that rewrites model IDs and dispatches through Cloudflare's gateway
  endpoint contract

### `codex`

Why it is blocked:
- `codex` targets the ChatGPT Codex backend API, not a standard Prism provider
- The runtime surface differs from the normal OpenAI API and should not be
  faked as generic OpenAI compatibility

What needs to change:
- Replace the current `unsupported` driver assignment with a real adapter
- Add a dedicated provider class under `src/Providers/`
- Route Codex-specific auth and request parameter normalization through that
  provider

Likely implementation shape:
- `src/Providers/Codex.php`
- use existing capability rules but pair them with an actual execution adapter

### `cohere`

Why it is blocked:
- Opencode uses `@ai-sdk/cohere`
- Prism does not currently expose a native Cohere provider here
- Cohere also has embeddings, so the adapter should not be text-only

What needs to change:
- Add a dedicated relay provider class under `src/Providers/`
- Add a `cohere` runtime driver mapping in `RelayManager`
- Ensure model metadata and embeddings support stay aligned

Likely implementation shape:
- `src/Providers/Cohere.php`
- support at least text, structured, and embeddings

### `custom`

Why it is blocked:
- `custom` is not a real provider; it is a user-defined runtime contract
- There is no stable, package-level meaning for it in `prism-relay` yet
- Treating it as a normal provider would make `prism-relay` responsible for
  somebody else's arbitrary transport definition

What needs to change:
- First decide scope: should `custom` live in this package at all?
- If yes, define a strict runtime interface for custom transports
- If no, keep it metadata-only and document that downstream apps own it

Likely implementation shape:
- either no adapter by design
- or a `CustomProviderFactory`-style extension point registered through
  `RelayManager`

### `deepinfra`

Why it is blocked:
- Opencode uses `@ai-sdk/deepinfra`
- Prism does not currently provide DeepInfra support in this package
- DeepInfra looks OpenAI-like in places, but should not be assumed to be
  drop-in compatible without an explicit adapter

What needs to change:
- Add a dedicated relay provider class
- Add a driver mapping in `RelayManager`
- Validate request/stream semantics against DeepInfra's actual API surface

Likely implementation shape:
- `src/Providers/DeepInfra.php`

### `gitlab`

Why it is blocked:
- Opencode uses GitLab's provider with custom auth, instance URL, headers, and
  runtime model discovery
- This is not a simple static base URL mapping

What needs to change:
- Add a dedicated relay provider class
- Add instance URL, auth, and discovery-related config handling
- Decide whether model discovery belongs in `prism-relay` runtime or remains an
  upstream-generated catalog concern only

Likely implementation shape:
- `src/Providers/GitLab.php`
- optional discovery helper if runtime-discovered models are in scope

### `google-vertex-anthropic`

Why it is blocked:
- This is Anthropic served through Vertex AI, not direct Anthropic
- The auth and endpoint contract differ from Prism's normal Anthropic provider

What needs to change:
- Add a dedicated adapter or a routed Vertex-Anthropic adapter
- Add project/location credential handling in `RelayManager`
- Keep model metadata under the generated registry while separating runtime auth

Likely implementation shape:
- `src/Providers/GoogleVertexAnthropic.php`
- or a generic Vertex adapter that can branch on provider family

### `sap-ai-core`

Why it is blocked:
- Opencode treats SAP AI Core as a special provider with service key auth and
  deployment/resource-group selection
- There is no existing Prism-native equivalent in this package

What needs to change:
- Add a dedicated relay provider class
- Add SAP-specific auth/config handling in `RelayManager`
- Decide which SAP runtime knobs belong in config versus request-time options

Likely implementation shape:
- `src/Providers/SapAiCore.php`

### `togetherai`

Why it is blocked:
- Opencode uses `@ai-sdk/togetherai`
- Prism does not currently provide a Together AI adapter here
- Some Together models may look OpenAI-like, but runtime behavior should still
  be adapter-backed, not guessed

What needs to change:
- Add a dedicated relay provider class
- Add a driver mapping in `RelayManager`
- Validate streaming/tool support against Together AI's actual semantics

Likely implementation shape:
- `src/Providers/TogetherAi.php`

### `v0`

Why it is blocked:
- Opencode treats `v0` as its own provider surface through `@ai-sdk/vercel`
- This is not currently represented as a Prism runtime provider in relay

What needs to change:
- Add a dedicated relay provider class if `v0` is intended to be runnable
- Add the runtime driver mapping in `RelayManager`
- Decide whether `v0` belongs as a separate runtime provider or as a special
  case of a broader Vercel adapter

Likely implementation shape:
- `src/Providers/V0.php`

### `venice`

Why it is blocked:
- Opencode uses `venice-ai-sdk-provider`
- Venice has provider-specific reasoning controls that are not represented by
  current Prism providers

What needs to change:
- Add a dedicated relay provider class
- Add a runtime driver mapping in `RelayManager`
- Thread Venice-specific reasoning parameters through request normalization

Likely implementation shape:
- `src/Providers/Venice.php`

## Cross-cutting work still needed

These providers are not just twelve isolated files. A clean implementation will
also require package-level decisions.

### 1. Decide adapter policy

Some providers are worth full runtime support now:

- `codex`
- `cohere`
- `deepinfra`
- `togetherai`
- `google-vertex-anthropic`
- `cloudflare-ai-gateway`

Some providers need a scope decision first:

- `custom`
- `gitlab`
- `sap-ai-core`
- `v0`

### 2. Keep metadata and runtime separate

The generated registry should remain the source of truth for:

- model IDs
- aliases
- defaults
- pricing
- context limits

Runtime adapters should only own:

- auth
- endpoint shape
- headers
- request/response translation
- stream semantics

### 3. Extend `RelayManager` without turning it into a switchboard dump

If several of these providers share patterns, prefer one reusable adapter shape
instead of twelve one-off classes. Likely reusable families:

- OpenAI-like but not Prism-native
- Anthropic-like but not direct Anthropic
- gateway/wrapper providers

### 4. Make unsupported status explicit

If any provider remains metadata-only by design, do not leave it as accidental
unsupported behavior. Document the decision in this file and keep the runtime
driver intentionally set to `unsupported`.

## Immediate next candidates

If the goal is maximum practical value for Iris first, the next adapters to
implement should be:

1. `codex`
2. `cloudflare-ai-gateway`
3. `google-vertex-anthropic`
4. `cohere`
5. `deepinfra`
6. `togetherai`

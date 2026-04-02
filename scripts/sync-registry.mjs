#!/usr/bin/env node

import { readFileSync, writeFileSync } from "node:fs";
import { spawnSync } from "node:child_process";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const sources = {
  opencode:
    process.env.OPENCODE_SNAPSHOT ||
    "/Users/rutger/Projects/kosmokrator/tmp/opencode/packages/opencode/src/provider/models-snapshot.ts",
  hermesModels:
    process.env.HERMES_MODELS ||
    "/Users/rutger/Projects/kosmokrator/tmp/hermes-agent/hermes_cli/models.py",
  hermesAuth:
    process.env.HERMES_AUTH ||
    "/Users/rutger/Projects/kosmokrator/tmp/hermes-agent/hermes_cli/auth.py",
  output: resolve(root, "config/relay.generated.php"),
};

const canonicalProviderMap = new Map([
  ["google", "gemini"],
  ["zai", "z-api"],
  ["z.ai", "z-api"],
  ["z-ai", "z-api"],
  ["zhipuai", "z-api"],
  ["zhipu", "z-api"],
  ["moonshot", "kimi-coding"],
  ["moonshotai", "kimi"],
  ["openai-codex", "codex"],
  ["github-copilot", "copilot"],
  ["github-models", "copilot"],
  ["github-model", "copilot"],
  ["vercel", "ai-gateway"],
  ["dashscope", "alibaba"],
  ["aliyun", "alibaba"],
]);

const providerAliases = new Map([
  ["glm", "z-api"],
  ["z.ai", "z-api"],
  ["z-ai", "z-api"],
  ["zhipu", "z-api"],
  ["github", "copilot"],
  ["github-copilot", "copilot"],
  ["github-models", "copilot"],
  ["github-model", "copilot"],
  ["github-copilot-acp", "copilot-acp"],
  ["copilot-acp-agent", "copilot-acp"],
  ["moonshot", "kimi-coding"],
  ["kimi", "kimi-coding"],
  ["minimax-china", "minimax-cn"],
  ["minimax_cn", "minimax-cn"],
  ["claude", "anthropic"],
  ["claude-code", "anthropic"],
  ["deep-seek", "deepseek"],
  ["opencode", "opencode-zen"],
  ["zen", "opencode-zen"],
  ["go", "opencode-go"],
  ["opencode-go-sub", "opencode-go"],
  ["aigateway", "ai-gateway"],
  ["vercel", "ai-gateway"],
  ["vercel-ai-gateway", "ai-gateway"],
  ["kilo", "kilocode"],
  ["kilo-code", "kilocode"],
  ["kilo-gateway", "kilocode"],
  ["dashscope", "alibaba"],
  ["aliyun", "alibaba"],
  ["qwen", "alibaba"],
  ["alibaba-cloud", "alibaba"],
  ["hf", "huggingface"],
  ["hugging-face", "huggingface"],
  ["huggingface-hub", "huggingface"],
]);

const explicitDriverMap = new Map([
  ["z-api", "glm"],
  ["z", "glm-coding"],
  ["kimi", "kimi"],
  ["kimi-coding", "kimi-coding"],
  ["minimax", "minimax"],
  ["minimax-cn", "minimax-cn"],
  ["opencode-go", "model-router"],
  ["copilot-acp", "external-process"],
  ["codex", "unsupported"],
  ["google-vertex", "google-vertex"],
  ["amazon-bedrock", "amazon-bedrock"],
]);

const npmDriverMap = new Map([
  ["@ai-sdk/openai", "openai"],
  ["@ai-sdk/openai-compatible", "openai-compatible"],
  ["@ai-sdk/openai-compatible-v2", "openai-compatible"],
  ["@ai-sdk/anthropic", "anthropic"],
  ["@ai-sdk/google", "gemini"],
  ["@ai-sdk/google-vertex", "google-vertex"],
  ["@ai-sdk/groq", "groq"],
  ["@ai-sdk/mistral", "mistral"],
  ["@ai-sdk/xai", "xai"],
  ["@ai-sdk/ollama", "ollama"],
  ["@ai-sdk/perplexity", "perplexity"],
  ["@openrouter/ai-sdk-provider", "openrouter"],
  ["@ai-sdk/gateway", "openai-compatible"],
  ["@ai-sdk/azure", "openai-compatible"],
  ["@ai-sdk/amazon-bedrock", "amazon-bedrock"],
]);

const capabilityOverrides = {
  z: { temperature: false, top_p: false, max_tokens: true, streaming: false },
  "z-api": { temperature: false, top_p: false, max_tokens: true, streaming: true },
  codex: { temperature: false, top_p: false, max_tokens: true, streaming: true },
  "kimi-coding": { temperature: false, top_p: false, max_tokens: true, streaming: true },
};

const defaultVersions = {
  anthropic: "2023-06-01",
  minimax: "2023-06-01",
  "minimax-cn": "2023-06-01",
};

const modelRoutes = {
  "opencode-go": [
    { match: ["MiniMax-", "minimax-"], driver: "anthropic-compatible" },
    { match: ["glm-", "kimi-"], driver: "openai-compatible" },
  ],
};

function loadOpencodeSnapshot(path) {
  const source = readFileSync(path, "utf8")
    .replace(/^\/\/.*\n/, "")
    .replace(/^export const snapshot = /, "return ")
    .replace(/\s+as const;?\s*$/, ";");

  return Function(source)();
}

function loadPythonAssignments(path, names) {
  const python = `
import ast
import json
import sys

source_path = sys.argv[1]
names = set(sys.argv[2:])
source = open(source_path, "r", encoding="utf-8").read()
tree = ast.parse(source, source_path)

def convert(node):
    if isinstance(node, ast.Constant):
        return node.value
    if isinstance(node, ast.Name):
        if node.id in env:
            return env[node.id]
        raise TypeError(f"Unknown name: {node.id}")
    if isinstance(node, ast.List):
        return [convert(item) for item in node.elts]
    if isinstance(node, ast.Tuple):
        return [convert(item) for item in node.elts]
    if isinstance(node, ast.Set):
        return [convert(item) for item in node.elts]
    if isinstance(node, ast.Dict):
        return {convert(key): convert(value) for key, value in zip(node.keys, node.values)}
    if isinstance(node, ast.UnaryOp) and isinstance(node.op, ast.USub):
        return -convert(node.operand)
    if isinstance(node, ast.JoinedStr):
        parts = []
        for value in node.values:
            if isinstance(value, ast.Constant):
                parts.append(str(value.value))
            elif isinstance(value, ast.FormattedValue):
                parts.append(str(convert(value.value)))
            else:
                raise TypeError(f"Unsupported joined string value: {ast.dump(value)}")
        return "".join(parts)
    if isinstance(node, ast.BinOp) and isinstance(node.op, ast.Add):
        return convert(node.left) + convert(node.right)
    if isinstance(node, ast.Call) and getattr(node.func, "id", None) == "ProviderConfig":
        return {kw.arg: convert(kw.value) for kw in node.keywords}
    raise TypeError(f"Unsupported node: {ast.dump(node)}")

env = {}
result = {}

def capture(name, node):
    try:
        value = convert(node)
    except TypeError:
        return

    env[name] = value

    if name in names:
        result[name] = value

for stmt in tree.body:
    if isinstance(stmt, ast.Assign):
        for target in stmt.targets:
            if isinstance(target, ast.Name):
                capture(target.id, stmt.value)
    elif isinstance(stmt, ast.AnnAssign) and isinstance(stmt.target, ast.Name):
        capture(stmt.target.id, stmt.value)

print(json.dumps(result))
`;

  const proc = spawnSync("python3", ["-c", python, path, ...names], {
    encoding: "utf8",
  });

  if (proc.status !== 0) {
    throw new Error(proc.stderr || `Failed to parse ${path}`);
  }

  return JSON.parse(proc.stdout);
}

function canonicalProviderId(name) {
  return canonicalProviderMap.get(String(name).toLowerCase()) || String(name).toLowerCase();
}

function addUnique(list, value) {
  if (!value) {
    return;
  }

  if (!list.includes(value)) {
    list.push(value);
  }
}

function ensureProvider(registry, providerId) {
  const canonical = canonicalProviderId(providerId);

  if (!registry[canonical]) {
    registry[canonical] = {
      aliases: [],
      models: {},
    };
  }

  if (canonical !== String(providerId).toLowerCase()) {
    addUnique(registry[canonical].aliases, String(providerId).toLowerCase());
  }

  return registry[canonical];
}

function ensureModel(provider, modelId) {
  if (!provider.models[modelId]) {
    provider.models[modelId] = {};
  }

  return provider.models[modelId];
}

function inferDriver(providerId, provider = {}) {
  if (explicitDriverMap.has(providerId)) {
    return explicitDriverMap.get(providerId);
  }

  if (provider.npm && npmDriverMap.has(provider.npm)) {
    return npmDriverMap.get(provider.npm);
  }

  if (provider.auth_type === "external_process") {
    return "external-process";
  }

  if (provider.api || provider.url) {
    return "openai-compatible";
  }

  return "unsupported";
}

function normalizeModel(modelId, model = {}) {
  const entry = {};

  if (model.name) {
    entry.display_name = model.name;
  }
  if (Number.isFinite(model?.limit?.context)) {
    entry.context = model.limit.context;
  }
  if (Number.isFinite(model?.limit?.output)) {
    entry.max_output = model.limit.output;
  }
  if (Number.isFinite(model?.cost?.input)) {
    entry.input = model.cost.input;
  }
  if (Number.isFinite(model?.cost?.output)) {
    entry.output = model.cost.output;
  }
  if (Number.isFinite(model?.cost?.cache_read)) {
    entry.cached_input = model.cost.cache_read;
  }
  if (Number.isFinite(model?.cost?.cache_write)) {
    entry.cached_write = model.cost.cache_write;
  }
  if (typeof model.reasoning === "boolean") {
    entry.thinking = model.reasoning;
  }
  if (typeof model.tool_call === "boolean") {
    entry.tool_call = model.tool_call;
  }
  if (typeof model.attachment === "boolean") {
    entry.attachments = model.attachment;
  }

  if (Object.keys(entry).length === 0) {
    entry.display_name = modelId;
  }

  return entry;
}

function mergeObjects(base, overlay) {
  for (const [key, value] of Object.entries(overlay)) {
    if (Array.isArray(value)) {
      base[key] = Array.isArray(base[key]) ? [...new Set([...base[key], ...value])] : [...value];
      continue;
    }

    if (value && typeof value === "object" && !Array.isArray(value)) {
      base[key] = mergeObjects(base[key] && typeof base[key] === "object" ? base[key] : {}, value);
      continue;
    }

    base[key] = value;
  }

  return base;
}

const opencode = loadOpencodeSnapshot(sources.opencode);
const hermesModels = loadPythonAssignments(sources.hermesModels, [
  "OPENROUTER_MODELS",
  "_PROVIDER_MODELS",
  "_PROVIDER_LABELS",
  "_PROVIDER_ALIASES",
]);
const hermesAuth = loadPythonAssignments(sources.hermesAuth, ["PROVIDER_REGISTRY"]);

const registry = {};

for (const [providerId, provider] of Object.entries(opencode)) {
  const canonical = canonicalProviderId(providerId);
  const entry = ensureProvider(registry, providerId);
  entry.driver = inferDriver(canonical, provider);
  entry.label ||= provider.name || provider.id;
  entry.url ||= provider.api;
  entry.npm ||= provider.npm;
  entry.doc ||= provider.doc;
  entry.env = Array.isArray(provider.env) ? provider.env : entry.env || [];

  for (const [modelId, model] of Object.entries(provider.models || {})) {
    mergeObjects(ensureModel(entry, modelId), normalizeModel(modelId, model));
  }
}

for (const [providerId, provider] of Object.entries(hermesAuth.PROVIDER_REGISTRY || {})) {
  const canonical = canonicalProviderId(providerId);
  const entry = ensureProvider(registry, providerId);
  const authProvider = {
    ...provider,
    url: provider.inference_base_url,
  };
  if (!entry.driver || entry.driver === "unsupported") {
    entry.driver = inferDriver(canonical, authProvider);
  }
  entry.label ||= provider.name || canonical;
  entry.url ||= provider.inference_base_url || entry.url;
  entry.auth_type = provider.auth_type || entry.auth_type;
  entry.env = Array.isArray(provider.api_key_env_vars) ? provider.api_key_env_vars : entry.env || [];

  if (provider.base_url_env_var) {
    entry.base_url_env_var = provider.base_url_env_var;
  }

  if (defaultVersions[canonical] && !entry.version) {
    entry.version = defaultVersions[canonical];
  }
}

for (const [providerId, label] of Object.entries(hermesModels._PROVIDER_LABELS || {})) {
  ensureProvider(registry, providerId).label ||= label;
}

for (const [alias, target] of Object.entries(hermesModels._PROVIDER_ALIASES || {})) {
  const canonical = canonicalProviderId(target);
  const entry = ensureProvider(registry, canonical);
  addUnique(entry.aliases, String(alias).toLowerCase());
  providerAliases.set(String(alias).toLowerCase(), canonical);
}

for (const [alias, target] of providerAliases.entries()) {
  const entry = ensureProvider(registry, target);
  addUnique(entry.aliases, alias);
}

for (const [providerId, models] of Object.entries(hermesModels._PROVIDER_MODELS || {})) {
  const entry = ensureProvider(registry, providerId);

  for (const modelId of models) {
    ensureModel(entry, modelId).display_name ||= modelId;
  }

  if (models.length > 0) {
    entry.default_model = models[0];
  }
}

for (const [modelId] of hermesModels.OPENROUTER_MODELS || []) {
  const entry = ensureProvider(registry, "openrouter");
  ensureModel(entry, modelId).display_name ||= modelId;

  if (!entry.default_model) {
    entry.default_model = modelId;
  }
}

for (const [providerId, entry] of Object.entries(registry)) {
  if (!entry.driver || entry.driver === "unsupported") {
    entry.driver = inferDriver(providerId, entry);
  }
  entry.capabilities ||= capabilityOverrides[providerId];
  entry.models ||= {};

  if (modelRoutes[providerId]) {
    entry.model_routes = modelRoutes[providerId];
    entry.fallback_driver ||= "openai-compatible";
  }

  if (!entry.default_model) {
    entry.default_model = Object.keys(entry.models)[0] || null;
  }

  entry.aliases = [...new Set((entry.aliases || []).filter(Boolean))].sort();

  const sortedModels = {};
  for (const modelId of Object.keys(entry.models).sort()) {
    const model = entry.models[modelId];
    if (model.aliases) {
      model.aliases = [...new Set(model.aliases)].sort();
    }
    sortedModels[modelId] = model;
  }
  entry.models = sortedModels;
}

const sortedRegistry = {};
for (const providerId of Object.keys(registry).sort()) {
  sortedRegistry[providerId] = registry[providerId];
}

const php = `<?php

/**
 * Auto-generated from Opencode and Hermes catalogs.
 * Run \`composer sync-registry\` to refresh this file.
 */
return ${toPhp(sortedRegistry)};
`;

writeFileSync(sources.output, php);

const providerCount = Object.keys(sortedRegistry).length;
const modelCount = Object.values(sortedRegistry).reduce(
  (sum, provider) => sum + Object.keys(provider.models || {}).length,
  0,
);

console.log(
  `Generated ${sources.output} with ${providerCount} providers and ${modelCount} provider-model entries.`,
);

function toPhp(value, indent = 0) {
  const pad = "    ".repeat(indent);
  const nextPad = "    ".repeat(indent + 1);

  if (value === undefined) {
    return "null";
  }
  if (value === null) {
    return "null";
  }
  if (typeof value === "string") {
      return "'" + value.replace(/\\/g, "\\\\").replace(/'/g, "\\'") + "'";
  }
  if (typeof value === "number") {
    return String(value);
  }
  if (typeof value === "boolean") {
    return value ? "true" : "false";
  }
  if (Array.isArray(value)) {
    if (value.length === 0) {
      return "[]";
    }
    const items = value.map((item) => `${nextPad}${toPhp(item, indent + 1)}`);
    return `[\n${items.join(",\n")}\n${pad}]`;
  }
  if (typeof value === "object") {
    const entries = Object.entries(value);
    if (entries.length === 0) {
      return "[]";
    }
    const items = entries.map(
      ([key, item]) => `${nextPad}${toPhp(String(key), 0)} => ${toPhp(item, indent + 1)}`,
    );
    return `[\n${items.join(",\n")}\n${pad}]`;
  }

  throw new Error(`Unsupported value type: ${typeof value}`);
}

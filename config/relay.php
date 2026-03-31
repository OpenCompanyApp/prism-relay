<?php

/**
 * Provider metadata registry for prism-relay.
 *
 * Each model entry supports:
 * - context:       context window in tokens
 * - max_output:    max output tokens
 * - input:         input price per million tokens (USD)
 * - output:        output price per million tokens (USD)
 * - cached_input:  cached input price per million (USD)
 * - cached_write:  cache write price per million (USD)
 * - thinking:      supports extended thinking / reasoning
 * - display_name:  human-readable model name
 */
return [
    'anthropic' => [
        'default_model' => 'claude-sonnet-4-5-20250929',
        'url' => 'https://api.anthropic.com/v1',
        'models' => [
            'claude-opus-4-5-20250929' => [
                'display_name' => 'Claude Opus 4.5',
                'context' => 200000, 'max_output' => 32768,
                'input' => 15.0, 'output' => 75.0,
                'cached_input' => 1.5, 'cached_write' => 18.75,
                'thinking' => true,
            ],
            'claude-sonnet-4-5-20250929' => [
                'display_name' => 'Claude Sonnet 4.5',
                'context' => 200000, 'max_output' => 16384,
                'input' => 3.0, 'output' => 15.0,
                'cached_input' => 0.3, 'cached_write' => 3.75,
                'thinking' => true,
            ],
            'claude-sonnet-4-20250514' => [
                'display_name' => 'Claude Sonnet 4',
                'context' => 200000, 'max_output' => 16384,
                'input' => 3.0, 'output' => 15.0,
                'cached_input' => 0.3, 'cached_write' => 3.75,
                'thinking' => true,
            ],
            'claude-haiku-4-5-20251001' => [
                'display_name' => 'Claude Haiku 4.5',
                'context' => 200000, 'max_output' => 8192,
                'input' => 0.80, 'output' => 4.0,
                'cached_input' => 0.08, 'cached_write' => 1.0,
            ],
            'claude-haiku-3-5-20241022' => [
                'display_name' => 'Claude Haiku 3.5',
                'context' => 200000, 'max_output' => 8192,
                'input' => 0.80, 'output' => 4.0,
                'cached_input' => 0.08, 'cached_write' => 1.0,
            ],
            'claude-opus-4-20250514' => [
                'display_name' => 'Claude Opus 4',
                'context' => 200000, 'max_output' => 32768,
                'input' => 15.0, 'output' => 75.0,
                'cached_input' => 1.5, 'cached_write' => 18.75,
                'thinking' => true,
            ],
        ],
    ],

    'openai' => [
        'default_model' => 'gpt-4o',
        'url' => 'https://api.openai.com/v1',
        'models' => [
            'gpt-4o' => [
                'display_name' => 'GPT-4o',
                'context' => 128000, 'max_output' => 16384,
                'input' => 2.50, 'output' => 10.0, 'cached_input' => 1.25,
            ],
            'gpt-4o-mini' => [
                'display_name' => 'GPT-4o Mini',
                'context' => 128000, 'max_output' => 16384,
                'input' => 0.15, 'output' => 0.60, 'cached_input' => 0.075,
            ],
            'gpt-4.1' => [
                'display_name' => 'GPT-4.1',
                'context' => 1047576, 'max_output' => 32768,
                'input' => 2.0, 'output' => 8.0, 'cached_input' => 0.50,
            ],
            'gpt-4.1-mini' => [
                'display_name' => 'GPT-4.1 Mini',
                'context' => 1047576, 'max_output' => 32768,
                'input' => 0.40, 'output' => 1.60, 'cached_input' => 0.10,
            ],
            'gpt-4.1-nano' => [
                'display_name' => 'GPT-4.1 Nano',
                'context' => 1047576, 'max_output' => 32768,
                'input' => 0.10, 'output' => 0.40, 'cached_input' => 0.025,
            ],
            'gpt-4-turbo' => [
                'display_name' => 'GPT-4 Turbo',
                'context' => 128000, 'max_output' => 4096,
                'input' => 10.0, 'output' => 30.0,
            ],
            'o1' => [
                'display_name' => 'o1',
                'context' => 200000, 'max_output' => 100000,
                'input' => 15.0, 'output' => 60.0,
                'thinking' => true,
            ],
            'o1-mini' => [
                'display_name' => 'o1 Mini',
                'context' => 128000, 'max_output' => 65536,
                'input' => 1.10, 'output' => 4.40,
                'thinking' => true,
            ],
            'o3' => [
                'display_name' => 'o3',
                'context' => 200000, 'max_output' => 100000,
                'input' => 10.0, 'output' => 40.0,
                'thinking' => true,
            ],
            'o3-mini' => [
                'display_name' => 'o3 Mini',
                'context' => 200000, 'max_output' => 100000,
                'input' => 1.10, 'output' => 4.40,
                'thinking' => true,
            ],
            'o4-mini' => [
                'display_name' => 'o4 Mini',
                'context' => 200000, 'max_output' => 100000,
                'input' => 1.10, 'output' => 4.40,
                'thinking' => true,
            ],
        ],
    ],

    'gemini' => [
        'default_model' => 'gemini-2.0-flash',
        'url' => 'https://generativelanguage.googleapis.com/v1beta',
        'models' => [
            'gemini-2.5-pro-preview-05-06' => [
                'display_name' => 'Gemini 2.5 Pro',
                'context' => 1048576, 'max_output' => 65536,
                'input' => 1.25, 'output' => 10.0,
                'thinking' => true,
            ],
            'gemini-2.5-flash-preview-05-20' => [
                'display_name' => 'Gemini 2.5 Flash',
                'context' => 1048576, 'max_output' => 65536,
                'input' => 0.15, 'output' => 0.60,
                'thinking' => true,
            ],
            'gemini-2.0-flash' => [
                'display_name' => 'Gemini 2.0 Flash',
                'context' => 1048576, 'max_output' => 8192,
                'input' => 0.10, 'output' => 0.40,
            ],
            'gemini-2.0-flash-lite' => [
                'display_name' => 'Gemini 2.0 Flash Lite',
                'context' => 1048576, 'max_output' => 8192,
                'input' => 0.075, 'output' => 0.30,
            ],
            'gemini-1.5-pro' => [
                'display_name' => 'Gemini 1.5 Pro',
                'context' => 2097152, 'max_output' => 8192,
                'input' => 1.25, 'output' => 5.0,
            ],
        ],
    ],

    'deepseek' => [
        'default_model' => 'deepseek-chat',
        'url' => 'https://api.deepseek.com',
        'models' => [
            'deepseek-chat' => [
                'display_name' => 'DeepSeek V3',
                'context' => 64000, 'max_output' => 8192,
                'input' => 0.27, 'output' => 1.10, 'cached_input' => 0.07,
            ],
            'deepseek-reasoner' => [
                'display_name' => 'DeepSeek R1',
                'context' => 64000, 'max_output' => 8192,
                'input' => 0.55, 'output' => 2.19, 'cached_input' => 0.14,
                'thinking' => true,
            ],
        ],
    ],

    'groq' => [
        'default_model' => 'llama-3.3-70b-versatile',
        'url' => 'https://api.groq.com/openai/v1',
        'models' => [
            'llama-3.3-70b-versatile' => [
                'display_name' => 'Llama 3.3 70B',
                'context' => 128000, 'max_output' => 32768,
                'input' => 0.59, 'output' => 0.79,
            ],
            'llama-3.1-8b-instant' => [
                'display_name' => 'Llama 3.1 8B',
                'context' => 128000, 'max_output' => 8192,
                'input' => 0.05, 'output' => 0.08,
            ],
            'mixtral-8x7b-32768' => [
                'display_name' => 'Mixtral 8x7B',
                'context' => 32768, 'max_output' => 4096,
                'input' => 0.24, 'output' => 0.24,
            ],
            'gemma2-9b-it' => [
                'display_name' => 'Gemma 2 9B',
                'context' => 8192, 'max_output' => 4096,
                'input' => 0.20, 'output' => 0.20,
            ],
        ],
    ],

    'mistral' => [
        'default_model' => 'mistral-large-latest',
        'url' => 'https://api.mistral.ai/v1',
        'models' => [
            'mistral-large-latest' => [
                'display_name' => 'Mistral Large',
                'context' => 128000, 'max_output' => 8192,
                'input' => 2.0, 'output' => 6.0,
            ],
            'mistral-small-latest' => [
                'display_name' => 'Mistral Small',
                'context' => 32000, 'max_output' => 8192,
                'input' => 0.20, 'output' => 0.60,
            ],
            'codestral-latest' => [
                'display_name' => 'Codestral',
                'context' => 256000, 'max_output' => 8192,
                'input' => 0.30, 'output' => 0.90,
            ],
            'mistral-nemo' => [
                'display_name' => 'Mistral Nemo',
                'context' => 128000, 'max_output' => 4096,
                'input' => 0.15, 'output' => 0.15,
            ],
        ],
    ],

    'xai' => [
        'default_model' => 'grok-2',
        'url' => 'https://api.x.ai/v1',
        'models' => [
            'grok-3' => [
                'display_name' => 'Grok 3',
                'context' => 131072, 'max_output' => 16384,
                'input' => 3.0, 'output' => 15.0,
                'thinking' => true,
            ],
            'grok-3-mini' => [
                'display_name' => 'Grok 3 Mini',
                'context' => 131072, 'max_output' => 16384,
                'input' => 0.30, 'output' => 0.50,
                'thinking' => true,
            ],
            'grok-2' => [
                'display_name' => 'Grok 2',
                'context' => 131072, 'max_output' => 8192,
                'input' => 2.0, 'output' => 10.0,
            ],
        ],
    ],

    'ollama' => [
        'default_model' => 'llama3.2',
        'url' => 'http://localhost:11434/v1',
        'models' => [
            'qwen2.5' => ['display_name' => 'Qwen 2.5', 'context' => 128000, 'max_output' => 8192],
            'qwen2' => ['display_name' => 'Qwen 2', 'context' => 32768, 'max_output' => 4096],
            'llama3.2' => ['display_name' => 'Llama 3.2', 'context' => 128000, 'max_output' => 4096],
            'phi-4' => ['display_name' => 'Phi 4', 'context' => 16384, 'max_output' => 4096],
            'phi-3' => ['display_name' => 'Phi 3', 'context' => 128000, 'max_output' => 4096],
        ],
    ],

    'openrouter' => [
        'default_model' => 'anthropic/claude-sonnet-4-5-20250929',
        'url' => 'https://openrouter.ai/api/v1',
        'models' => [],
    ],

    'perplexity' => [
        'default_model' => 'sonar-pro',
        'url' => 'https://api.perplexity.ai',
        'models' => [
            'sonar-pro' => ['display_name' => 'Sonar Pro', 'context' => 200000, 'max_output' => 8192],
            'sonar' => ['display_name' => 'Sonar', 'context' => 128000, 'max_output' => 8192],
        ],
    ],

    // --- Codex (ChatGPT subscription, registered by prism-codex) ---

    'codex' => [
        'default_model' => 'gpt-5.3-codex',
        'url' => 'https://chatgpt.com/backend-api/codex',
        'models' => [
            'gpt-5.3-codex' => ['display_name' => 'GPT-5.3 Codex', 'context' => 128000, 'max_output' => 16384],
            'gpt-5.2-codex' => ['display_name' => 'GPT-5.2 Codex', 'context' => 128000, 'max_output' => 16384],
            'gpt-5.1-codex' => ['display_name' => 'GPT-5.1 Codex', 'context' => 128000, 'max_output' => 16384],
            'gpt-5-codex' => ['display_name' => 'GPT-5 Codex', 'context' => 128000, 'max_output' => 16384],
            'gpt-5-codex-mini' => ['display_name' => 'GPT-5 Codex Mini', 'context' => 128000, 'max_output' => 16384],
        ],
    ],

    // --- Relay custom providers ---

    'glm' => [
        'default_model' => 'glm-4-plus',
        'url' => 'https://open.bigmodel.cn/api/paas/v4',
        'models' => [
            'glm-5.1' => [
                'display_name' => 'GLM 5.1',
                'context' => 204800, 'max_output' => 131072,
                'input' => 1.0, 'output' => 3.20,
                'thinking' => true,
            ],
            'glm-5' => [
                'display_name' => 'GLM 5',
                'context' => 204800, 'max_output' => 131072,
                'input' => 1.0, 'output' => 3.20,
                'thinking' => true,
            ],
            'glm-5-turbo' => [
                'display_name' => 'GLM 5 Turbo',
                'context' => 204800, 'max_output' => 16384,
                'input' => 0.50, 'output' => 1.50,
            ],
            'glm-4-plus' => [
                'display_name' => 'GLM 4 Plus',
                'context' => 128000, 'max_output' => 4096,
            ],
            'glm-4-flash' => [
                'display_name' => 'GLM 4 Flash',
                'context' => 128000, 'max_output' => 4096,
            ],
        ],
    ],

    'glm-coding' => [
        'default_model' => 'glm-4.7',
        'url' => 'https://api.z.ai/api/coding/paas/v4',
        'models' => [
            'glm-4.7' => [
                'display_name' => 'GLM 4.7',
                'context' => 128000, 'max_output' => 16384,
            ],
        ],
    ],

    'kimi' => [
        'default_model' => 'kimi-k2.5',
        'url' => 'https://api.moonshot.ai/v1',
        'models' => [
            'kimi-k2.5' => [
                'display_name' => 'Kimi K2.5',
                'context' => 131072, 'max_output' => 8192,
                'thinking' => true,
            ],
            'moonshot-v1-128k' => [
                'display_name' => 'Moonshot v1 128K',
                'context' => 131072, 'max_output' => 8192,
            ],
        ],
    ],

    'kimi-coding' => [
        'default_model' => 'kimi-k2.5',
        'url' => 'https://api.moonshot.ai/v1',
        'models' => [
            'kimi-k2.5' => [
                'display_name' => 'Kimi K2.5',
                'context' => 131072, 'max_output' => 8192,
                'thinking' => true,
            ],
        ],
    ],

    'minimax' => [
        'default_model' => 'MiniMax-M1',
        'url' => 'https://api.minimax.io/anthropic/v1',
        'models' => [
            'MiniMax-M1' => [
                'display_name' => 'MiniMax M1',
                'context' => 1000000, 'max_output' => 131072,
            ],
        ],
    ],

    'minimax-cn' => [
        'default_model' => 'MiniMax-M1',
        'url' => 'https://api.minimaxi.com/anthropic/v1',
        'models' => [
            'MiniMax-M1' => [
                'display_name' => 'MiniMax M1',
                'context' => 1000000, 'max_output' => 131072,
            ],
        ],
    ],
];

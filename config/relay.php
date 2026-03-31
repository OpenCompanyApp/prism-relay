<?php

/**
 * Provider metadata registry for prism-relay.
 *
 * Each provider entry contains:
 * - default_model: the model used when none is specified
 * - url: the default API endpoint
 * - models: map of model name => [context, input price, output price, cached_input price]
 *   Prices are USD per million tokens. Omitted fields are null.
 */
return [
    'anthropic' => [
        'default_model' => 'claude-sonnet-4-5-20250929',
        'url' => 'https://api.anthropic.com/v1',
        'models' => [
            'claude-opus-4-5-20250929' => ['context' => 200000, 'input' => 15.0, 'output' => 75.0, 'cached_input' => 1.5],
            'claude-sonnet-4-5-20250929' => ['context' => 200000, 'input' => 3.0, 'output' => 15.0, 'cached_input' => 0.3],
            'claude-haiku-3-5-20241022' => ['context' => 200000, 'input' => 0.80, 'output' => 4.0, 'cached_input' => 0.08],
        ],
    ],
    'openai' => [
        'default_model' => 'gpt-4o',
        'url' => 'https://api.openai.com/v1',
        'models' => [
            'gpt-4o' => ['context' => 128000, 'input' => 2.50, 'output' => 10.0, 'cached_input' => 1.25],
            'gpt-4o-mini' => ['context' => 128000, 'input' => 0.15, 'output' => 0.60, 'cached_input' => 0.075],
            'o1' => ['context' => 200000, 'input' => 15.0, 'output' => 60.0],
            'o1-mini' => ['context' => 128000, 'input' => 1.10, 'output' => 4.40],
            'o3-mini' => ['context' => 200000, 'input' => 1.10, 'output' => 4.40],
        ],
    ],
    'gemini' => [
        'default_model' => 'gemini-2.0-flash',
        'url' => 'https://generativelanguage.googleapis.com/v1beta',
        'models' => [
            'gemini-2.5-pro-preview-05-06' => ['context' => 1048576, 'input' => 1.25, 'output' => 10.0],
            'gemini-2.0-flash' => ['context' => 1048576, 'input' => 0.10, 'output' => 0.40],
            'gemini-2.0-flash-lite' => ['context' => 1048576, 'input' => 0.075, 'output' => 0.30],
            'gemini-1.5-pro' => ['context' => 2097152, 'input' => 1.25, 'output' => 5.0],
        ],
    ],
    'deepseek' => [
        'default_model' => 'deepseek-chat',
        'url' => 'https://api.deepseek.com',
        'models' => [
            'deepseek-chat' => ['context' => 64000, 'input' => 0.27, 'output' => 1.10, 'cached_input' => 0.07],
            'deepseek-reasoner' => ['context' => 64000, 'input' => 0.55, 'output' => 2.19, 'cached_input' => 0.14],
        ],
    ],
    'groq' => [
        'default_model' => 'llama-3.3-70b-versatile',
        'url' => 'https://api.groq.com/openai/v1',
        'models' => [
            'llama-3.3-70b-versatile' => ['context' => 128000, 'input' => 0.59, 'output' => 0.79],
            'llama-3.1-8b-instant' => ['context' => 128000, 'input' => 0.05, 'output' => 0.08],
            'mixtral-8x7b-32768' => ['context' => 32768, 'input' => 0.24, 'output' => 0.24],
            'gemma2-9b-it' => ['context' => 8192, 'input' => 0.20, 'output' => 0.20],
        ],
    ],
    'mistral' => [
        'default_model' => 'mistral-large-latest',
        'url' => 'https://api.mistral.ai/v1',
        'models' => [
            'mistral-large-latest' => ['context' => 128000, 'input' => 2.0, 'output' => 6.0],
            'mistral-small-latest' => ['context' => 32000, 'input' => 0.20, 'output' => 0.60],
            'codestral-latest' => ['context' => 256000, 'input' => 0.30, 'output' => 0.90],
        ],
    ],
    'xai' => [
        'default_model' => 'grok-2',
        'url' => 'https://api.x.ai/v1',
        'models' => [
            'grok-2' => ['context' => 131072, 'input' => 2.0, 'output' => 10.0],
            'grok-3' => ['context' => 131072, 'input' => 3.0, 'output' => 15.0],
            'grok-3-mini' => ['context' => 131072, 'input' => 0.30, 'output' => 0.50],
        ],
    ],
    'ollama' => [
        'default_model' => 'llama3.2',
        'url' => 'http://localhost:11434/v1',
        'models' => [],
    ],
    'openrouter' => [
        'default_model' => 'anthropic/claude-sonnet-4-5-20250929',
        'url' => 'https://openrouter.ai/api/v1',
        'models' => [],
    ],

    // --- Relay custom providers ---

    'glm' => [
        'default_model' => 'glm-4-plus',
        'url' => 'https://open.bigmodel.cn/api/paas/v4',
        'models' => [
            'glm-4-plus' => ['context' => 128000],
            'glm-4-flash' => ['context' => 128000],
            'glm-5' => ['context' => 128000],
        ],
    ],
    'glm-coding' => [
        'default_model' => 'glm-4.7',
        'url' => 'https://api.z.ai/api/coding/paas/v4',
        'models' => [
            'glm-4.7' => ['context' => 128000],
        ],
    ],
    'kimi' => [
        'default_model' => 'kimi-k2.5',
        'url' => 'https://api.moonshot.ai/v1',
        'models' => [
            'kimi-k2.5' => ['context' => 131072],
            'moonshot-v1-128k' => ['context' => 131072],
        ],
    ],
    'kimi-coding' => [
        'default_model' => 'kimi-k2.5',
        'url' => 'https://api.moonshot.ai/v1',
        'models' => [
            'kimi-k2.5' => ['context' => 131072],
        ],
    ],
    'minimax' => [
        'default_model' => 'MiniMax-M1',
        'url' => 'https://api.minimax.io/anthropic/v1',
        'models' => [
            'MiniMax-M1' => ['context' => 1000000],
        ],
    ],
    'minimax-cn' => [
        'default_model' => 'MiniMax-M1',
        'url' => 'https://api.minimaxi.com/anthropic/v1',
        'models' => [
            'MiniMax-M1' => ['context' => 1000000],
        ],
    ],
];

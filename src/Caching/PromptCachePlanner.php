<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Caching;

use Prism\Prism\Contracts\Message;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class PromptCachePlanner
{
    /**
     * @param  SystemMessage[]  $systemPrompts
     * @param  Message[]  $messages
     */
    public static function plan(
        string $provider,
        array $systemPrompts,
        array $messages,
        ?string $cachedContentName = null,
        int $recentMessagesToCache = 1,
    ): PromptCachePlan {
        $plannedSystemPrompts = array_map(self::cloneSystemMessage(...), $systemPrompts);
        $plannedMessages = array_map(self::cloneMessage(...), $messages);
        $providerOptions = CacheStrategy::providerOptions($provider, $cachedContentName);

        if (CacheStrategy::capability($provider) !== CacheCapability::Ephemeral) {
            return new PromptCachePlan(
                systemPrompts: $plannedSystemPrompts,
                messages: $plannedMessages,
                providerOptions: $providerOptions,
            );
        }

        $messageOptions = CacheStrategy::messageOptions($provider);

        if ($plannedSystemPrompts !== []) {
            $plannedSystemPrompts[0]->withProviderOptions($messageOptions);
        }

        if ($recentMessagesToCache > 0) {
            $cached = 0;

            for ($i = count($plannedMessages) - 1; $i >= 0; $i--) {
                if (! self::supportsMessageCaching($plannedMessages[$i])) {
                    continue;
                }

                $plannedMessages[$i]->withProviderOptions($messageOptions);
                $cached++;

                if ($cached >= $recentMessagesToCache) {
                    break;
                }
            }
        }

        return new PromptCachePlan(
            systemPrompts: $plannedSystemPrompts,
            messages: $plannedMessages,
            providerOptions: $providerOptions,
        );
    }

    private static function supportsMessageCaching(Message $message): bool
    {
        return $message instanceof UserMessage
            || $message instanceof AssistantMessage
            || $message instanceof ToolResultMessage
            || $message instanceof SystemMessage;
    }

    private static function cloneMessage(Message $message): Message
    {
        return match (true) {
            $message instanceof UserMessage => self::cloneUserMessage($message),
            $message instanceof AssistantMessage => self::cloneAssistantMessage($message),
            $message instanceof ToolResultMessage => self::cloneToolResultMessage($message),
            $message instanceof SystemMessage => self::cloneSystemMessage($message),
            default => $message,
        };
    }

    private static function cloneSystemMessage(SystemMessage $message): SystemMessage
    {
        $copy = new SystemMessage($message->content);
        $copy->withProviderOptions($message->providerOptions());

        return $copy;
    }

    private static function cloneUserMessage(UserMessage $message): UserMessage
    {
        $copy = new UserMessage($message->content, $message->additionalContent, $message->additionalAttributes);
        $copy->withProviderOptions($message->providerOptions());

        return $copy;
    }

    private static function cloneAssistantMessage(AssistantMessage $message): AssistantMessage
    {
        $copy = new AssistantMessage($message->content, $message->toolCalls, $message->additionalContent);
        $copy->withProviderOptions($message->providerOptions());

        return $copy;
    }

    private static function cloneToolResultMessage(ToolResultMessage $message): ToolResultMessage
    {
        $copy = new ToolResultMessage($message->toolResults);
        $copy->withProviderOptions($message->providerOptions());

        return $copy;
    }
}

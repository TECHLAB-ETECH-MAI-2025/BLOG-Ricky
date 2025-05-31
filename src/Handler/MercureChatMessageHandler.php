<?php

namespace App\Handler;

use App\Message\MercureChatMessage;
use App\Service\MercureService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MercureChatMessageHandler
{
    private MercureService $mercureService;

    public function __construct(MercureService $mercureService)
    {
        $this->mercureService = $mercureService;
    }

    public function __invoke(MercureChatMessage $message): void
    {
        try {
            $this->mercureService->publishChatMessage(
                $message->getSenderId(),
                $message->getReceiverId(),
                $message->getData()
            );
        } catch (\Throwable $e) {
            error_log('[MercureChatMessageHandler] Erreur lors de la publication : ' . $e->getMessage());
        }
    }
}

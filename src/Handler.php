<?php

namespace Ragnarok\Skald;

use Monolog\Handler\HandlerInterface;
use Monolog\LogRecord;
use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Rest\Helpers\Channel\EmbedBuilder;
use Ragnarok\Fenrir\Rest\Helpers\Channel\MessageBuilder;
use Ragnarok\Skald\Exceptions\RestNotInitializedException;
use Stringable;

class Handler implements HandlerInterface
{
    public function __construct(
        private readonly Discord $discord,
        private readonly string $channelId,
    ) {
        if (!isset($this->discord->rest)) {
            throw new RestNotInitializedException('Rest should be initialized before use');
        }
    }

    public function isHandling(LogRecord $record): bool
    {
        return true;
    }

    public function handle(LogRecord $record): bool
    {
        $this->discord->rest->channel->createMessage(
            $this->channelId,
            MessageBuilder::new()
                ->addEmbed($this->getEmbed($record))
        )->done();

        return true;
    }

    private function getEmbed(LogRecord $record): EmbedBuilder
    {
        $embed = EmbedBuilder::new()
            ->setTitle($record->level->value)
            ->setDescription($this->limit($record->message, 4096));

        if (count($record->context) > 20) {
            return $embed->addField('Context', json_encode($record->context));
        }

        foreach ($record->context as $key => $value) {
            $embed->addField(
                (string) $key,
                $this->limit($this->contextItemToString($value), 1024)
            );
        }
    }

    private function contextItemToString(mixed $context): string
    {
        if (is_string($context)) {
            return $context;
        }

        if ($context instanceof Stringable) {
            return (string) $context;
        }

        return json_encode($context);
    }

    private function limit(string $toLimit, int $limit): string
    {
        if (strlen($toLimit) <= $limit) {
            return $toLimit;
        }

        return substr($toLimit, 0, $limit - 3) . '...';
    }

    public function handleBatch(array $records): void
    {
        foreach ($records as $record) {
            $this->handle($record);
        }
    }

    public function close(): void
    {
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Usage event ledger (idempotency key) and Messenger outbox/DLQ table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE usage_events (id UUID NOT NULL, organization_id UUID NOT NULL, event_id VARCHAR(128) NOT NULL, bytes BIGINT NOT NULL, status VARCHAR(32) NOT NULL, received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN usage_events.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN usage_events.organization_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE UNIQUE INDEX uniq_usage_events_organization_event ON usage_events (organization_id, event_id)');
        $this->addSql('CREATE INDEX idx_usage_events_organization_received ON usage_events (organization_id, received_at)');
        $this->addSql('ALTER TABLE usage_events ADD CONSTRAINT fk_usage_events_organization FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_messenger_messages_queue_name ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX idx_messenger_messages_available_at ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX idx_messenger_messages_delivered_at ON messenger_messages (delivered_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usage_events DROP CONSTRAINT fk_usage_events_organization');
        $this->addSql('DROP TABLE usage_events');
        $this->addSql('DROP TABLE messenger_messages');
    }
}

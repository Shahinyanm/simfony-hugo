<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Quota threshold notification ledger (one notification per crossed threshold)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organization_quota_notifications (organization_id UUID NOT NULL, threshold_percent SMALLINT NOT NULL, notified_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(organization_id, threshold_percent))');
        $this->addSql('COMMENT ON COLUMN organization_quota_notifications.organization_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE organization_quota_notifications ADD CONSTRAINT fk_quota_notifications_organization FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization_quota_notifications DROP CONSTRAINT fk_quota_notifications_organization');
        $this->addSql('DROP TABLE organization_quota_notifications');
    }
}

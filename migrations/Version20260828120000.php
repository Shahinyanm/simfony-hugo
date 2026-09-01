<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Organizations and credentials';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organizations (id UUID NOT NULL, name VARCHAR(255) NOT NULL, api_key VARCHAR(64) NOT NULL, quota_bytes BIGINT NOT NULL, consumption_bytes BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_organizations_api_key ON organizations (api_key)');
        $this->addSql('COMMENT ON COLUMN organizations.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE credentials (id UUID NOT NULL, organization_id UUID NOT NULL, username VARCHAR(255) NOT NULL, enabled BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_credentials_organization_id ON credentials (organization_id)');
        $this->addSql('COMMENT ON COLUMN credentials.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN credentials.organization_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE credentials ADD CONSTRAINT fk_credentials_organization FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credentials DROP CONSTRAINT fk_credentials_organization');
        $this->addSql('DROP TABLE credentials');
        $this->addSql('DROP TABLE organizations');
    }
}

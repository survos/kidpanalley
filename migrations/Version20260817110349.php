<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260817110349 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add command_process; replace per-entity pending_steps with file_asset.s3';
    }

    public function up(Schema $schema): void
    {
        // Written idempotently on purpose. The production kidpanalley predates this
        // migration and already carries file_asset.s3 as varchar(512) NULL, so a bare
        // ADD aborts the deploy. IF NOT EXISTS / IF EXISTS lets the same migration
        // apply to both the drifted production schema and a freshly built one.
        $this->addSql('CREATE TABLE IF NOT EXISTS command_process (id VARCHAR(26) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, command VARCHAR(255) NOT NULL, cli TEXT DEFAULT NULL, mode VARCHAR(16) NOT NULL, host VARCHAR(128) DEFAULT NULL, pid INT DEFAULT NULL, status VARCHAR(16) NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, exit_code INT DEFAULT NULL, memory_bytes INT DEFAULT NULL, output TEXT DEFAULT NULL, failure_message TEXT DEFAULT NULL, slots JSON DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_command_process_status ON command_process (status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_command_process_command ON command_process (command)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_command_process_created ON command_process (created_at)');
        $this->addSql('ALTER TABLE audio DROP COLUMN IF EXISTS pending_steps');
        $this->addSql('ALTER TABLE file_asset ADD COLUMN IF NOT EXISTS s3 VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE file_asset DROP COLUMN IF EXISTS pending_steps');
        $this->addSql('ALTER TABLE song DROP COLUMN IF EXISTS pending_steps');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE command_process');
        $this->addSql('ALTER TABLE audio ADD pending_steps JSON DEFAULT \'{}\' NOT NULL');
        $this->addSql('ALTER TABLE file_asset ADD pending_steps JSON DEFAULT \'{}\' NOT NULL');
        $this->addSql('ALTER TABLE file_asset DROP s3');
        $this->addSql('ALTER TABLE song ADD pending_steps JSON DEFAULT \'{}\' NOT NULL');
    }
}

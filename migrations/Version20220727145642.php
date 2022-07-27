<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220727145642 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_1483a5e9d4327649');
        $this->addSql('ALTER TABLE users DROP github_id');
        $this->addSql('ALTER TABLE users DROP facebook_id');
        $this->addSql('ALTER TABLE users DROP google_id');
        $this->addSql('ALTER TABLE video ADD raw_data JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE video DROP raw_data');
        $this->addSql('ALTER TABLE users ADD github_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD facebook_id VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD google_id VARCHAR(16) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_1483a5e9d4327649 ON users (github_id)');
    }
}

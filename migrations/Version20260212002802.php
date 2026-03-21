<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260212002802 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE file_asset ADD school VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE file_asset ADD year INT DEFAULT NULL');
        $this->addSql('ALTER TABLE file_asset ADD mime_type VARCHAR(127) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE file_asset DROP school');
        $this->addSql('ALTER TABLE file_asset DROP year');
        $this->addSql('ALTER TABLE file_asset DROP mime_type');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251002192641 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE song ADD title_backing TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE song DROP title');
        $this->addSql('ALTER TABLE song DROP translations');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE song ADD title TEXT NOT NULL');
        $this->addSql('ALTER TABLE song ADD translations JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE song DROP title_backing');
    }
}

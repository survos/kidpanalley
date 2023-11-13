<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231113123631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE school_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE song_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE users_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE video_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE school (id INT NOT NULL, name VARCHAR(255) NOT NULL, city VARCHAR(32) DEFAULT NULL, state VARCHAR(32) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE song (id INT NOT NULL, title TEXT NOT NULL, date DATE DEFAULT NULL, year INT DEFAULT NULL, school VARCHAR(255) DEFAULT NULL, lyrics TEXT DEFAULT NULL, featured_artist TEXT DEFAULT NULL, recording_credits TEXT DEFAULT NULL, musicians TEXT DEFAULT NULL, writers TEXT DEFAULT NULL, wordpress_page_id INT DEFAULT NULL, recording TEXT DEFAULT NULL, publisher TEXT DEFAULT NULL, notes TEXT DEFAULT NULL, lyrics_length INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE users (id INT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('CREATE TABLE video (id INT NOT NULL, youtube_id VARCHAR(32) DEFAULT NULL, title VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, date DATE DEFAULT NULL, raw_data JSON DEFAULT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE school_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE song_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE users_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE video_id_seq CASCADE');
        $this->addSql('DROP TABLE school');
        $this->addSql('DROP TABLE song');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE video');
    }
}

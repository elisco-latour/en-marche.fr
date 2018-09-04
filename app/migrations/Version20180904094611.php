<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180904094611 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE person_executive_office (id INT UNSIGNED AUTO_INCREMENT NOT NULL, first_name VARCHAR(50) NOT NULL, last_name VARCHAR(50) NOT NULL, executive_officer TINYINT(1) DEFAULT \'0\' NOT NULL, job LONGTEXT NOT NULL, slug VARCHAR(255) NOT NULL, published TINYINT(1) DEFAULT \'0\' NOT NULL, description VARCHAR(255) DEFAULT NULL, content VARCHAR(800) DEFAULT NULL, facebook_profile VARCHAR(255) DEFAULT NULL, twitter_profile VARCHAR(255) DEFAULT NULL, instagram_profile VARCHAR(255) DEFAULT NULL, linked_in_profile VARCHAR(255) DEFAULT NULL, uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX executive_office_person_uuid_unique (uuid), UNIQUE INDEX executive_office_person_slug_unique (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE person_executive_office');
    }
}

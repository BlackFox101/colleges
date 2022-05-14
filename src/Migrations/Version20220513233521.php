<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220513233521 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create college table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE college (college_id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, city VARCHAR(255) DEFAULT NULL, state VARCHAR(50) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, site VARCHAR(255) DEFAULT NULL, image_url VARCHAR(255) DEFAULT NULL, college_page_url VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL, updated_at TIMESTAMP NULL, is_deprecated TINYINT(1) DEFAULT 0 NOT NULL, PRIMARY KEY(college_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE college');
    }
}

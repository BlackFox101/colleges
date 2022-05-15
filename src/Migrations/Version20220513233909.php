<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220513233909 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index for the name field in college';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX name_idx ON college(name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX name_idx ON college');
    }
}

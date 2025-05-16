<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250516145849 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE article_like (id INT AUTO_INCREMENT NOT NULL, article_id INT NOT NULL, ip_address VARCHAR(45) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_1C21C7B27294869C (article_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_like ADD CONSTRAINT FK_1C21C7B27294869C FOREIGN KEY (article_id) REFERENCES article (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE category CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE article_like DROP FOREIGN KEY FK_1C21C7B27294869C
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE article_like
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article CHANGE created_at created_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE category CHANGE created_at created_at DATETIME NOT NULL
        SQL);
    }
}

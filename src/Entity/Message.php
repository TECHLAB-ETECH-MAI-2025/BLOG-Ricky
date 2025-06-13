<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sentMessages')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'expéditeur est requis.')]
    private ?User $sender = null;

    #[ORM\ManyToOne(inversedBy: 'receivedMessages')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le destinataire est requis.')]
    private ?User $receiver = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le message ne peut pas être vide.')]
    #[Assert\Length(
        max: 5000,
        maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $content = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de création est requise.')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isRead = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): static
    {
        $this->sender = $sender;

        return $this;
    }

    public function getReceiver(): ?User
    {
        return $this->receiver;
    }

    public function setReceiver(?User $receiver): static
    {
        $this->receiver = $receiver;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        return $this;
    }
}

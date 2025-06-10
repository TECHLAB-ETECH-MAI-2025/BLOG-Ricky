<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function findConversation(int $userId1, int $userId2): array
    {
        return $this->createQueryBuilder('m')
            ->where('(m.sender = :user1 AND m.receiver = :user2) OR (m.sender = :user2 AND m.receiver = :user1)')
            ->setParameter('user1', $userId1)
            ->setParameter('user2', $userId2)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getUnreadCountsPerSender(int $receiverId): array
    {
        return $this->createQueryBuilder('m')
            ->select('IDENTITY(m.sender) as senderId, COUNT(m.id) as unreadCount')
            ->where('m.receiver = :receiver')
            ->andWhere('m.isRead = false')
            ->setParameter('receiver', $receiverId)
            ->groupBy('m.sender')
            ->getQuery()
            ->getResult();
    }

    public function markConversationAsRead(int $senderId, int $receiverId): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.isRead', true)
            ->where('m.sender = :sender')
            ->andWhere('m.receiver = :receiver')
            ->andWhere('m.isRead = false')
            ->setParameter('sender', $senderId)
            ->setParameter('receiver', $receiverId)
            ->getQuery()
            ->execute();
    }
}

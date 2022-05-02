<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\CollegeRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CollegeRepository::class)]
class College
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $collegeId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private string|null $city;

    #[ORM\Column(type: 'string', length: 2, nullable: true)]
    private string|null $state;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private string|null $address;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private string|null $phone;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private string|null $site;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private string|null $imageUrl;

    #[ORM\Column(type: 'datetime', columnDefinition: 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL')]
    private DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true, columnDefinition: 'TIMESTAMP NULL')]
    private ?DateTimeInterface $updatedAt;

    public function __construct(string $name)
    {
        $this->name = $name;
    }


    public function getId(): ?int
    {
        return $this->collegeId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getSite(): ?string
    {
        return $this->site;
    }

    public function setSite(string $site): self
    {
        $this->site = $site;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}

<?php

namespace App\Models;

class FooterSetting
{
    public function __construct(
        public int $id = 1,
        public string $brandInitials = 'FN',
        public string $brandName = 'Farshad Nabizade',
        public string $brandTitle = 'Full-Stack Software Engineer',
        public string $brandBio = 'Dedicated to architecting high-performance web applications, scalable APIs, reactive frontends, and comprehensive computer science knowledge bases.',
        public string $availabilityStatus = 'Available for Engineering Opportunities',
        public array $navLinks = [],
        public array $technologies = [],
        public string $email = 'farshad.nabizade@gmail.com',
        public string $phone = '+98 912 345 6789',
        public string $location = 'Tehran, Iran',
        public string $contactNote = 'Feel free to reach out for collaborations, technical inquiries, or consulting.',
        public string $copyrightText = 'All rights reserved. • Engineered with modern web standards.',
        public array $visibleSections = [],
        public ?string $updatedAt = null
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'brand_initials' => $this->brandInitials,
            'brand_name' => $this->brandName,
            'brand_title' => $this->brandTitle,
            'brand_bio' => $this->brandBio,
            'availability_status' => $this->availabilityStatus,
            'nav_links' => $this->navLinks,
            'technologies' => $this->technologies,
            'email' => $this->email,
            'phone' => $this->phone,
            'location' => $this->location,
            'contact_note' => $this->contactNote,
            'copyright_text' => $this->copyrightText,
            'visible_sections' => $this->visibleSections,
            'updated_at' => $this->updatedAt,
        ];
    }
}

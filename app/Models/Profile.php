<?php

namespace App\Models;

class Profile
{
    public function __construct(
        public int $id = 1,
        public string $fullName = 'Farshad Nabizade',
        public string $headline = 'Full-Stack & Systems Engineer',
        public string $email = 'farshadnabizadeh1993@gmail.com',
        public string $phone = '+98 921 812 4475',
        public string $address = 'Tehran, Iran',
        public int $age = 34,
        public string $gender = 'Male',
        public string $maritalStatus = 'Single',
        public string $militaryStatus = 'Exempted',
        public int $workExperienceYears = 7,
        public string $salaryExpectation = '45 - 60 Million Tomans',
        public string $educationDegree = 'B.Sc. in Computer Engineering',
        public string $educationSchool = 'Islamic Azad University (IAU)',
        public string $educationYears = '2016 – 2019',
        public string $educationGpa = '16.4 / 20',
        public string $aboutMe = '',
        public string $github = 'https://github.com/farshadnabizadeh',
        public string $linkedin = 'https://linkedin.com/in/farshad-nabizadeh-37457b215',
        public array $skills = [],
        public array $experiences = [],
        public array $courses = [],
        public array $languages = [],
        public array $software = [],
        public string $website = 'https://blog.sibmlm.ir',
        public ?string $updatedAt = null
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->fullName,
            'headline' => $this->headline,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'age' => $this->age,
            'gender' => $this->gender,
            'marital_status' => $this->maritalStatus,
            'military_status' => $this->militaryStatus,
            'work_experience_years' => $this->workExperienceYears,
            'salary_expectation' => $this->salaryExpectation,
            'education_degree' => $this->educationDegree,
            'education_school' => $this->educationSchool,
            'education_years' => $this->educationYears,
            'education_gpa' => $this->educationGpa,
            'about_me' => $this->aboutMe,
            'github' => $this->github,
            'linkedin' => $this->linkedin,
            'website' => $this->website,
            'skills' => $this->skills,
            'experiences' => $this->experiences,
            'courses' => $this->courses,
            'languages' => $this->languages,
            'software' => $this->software,
            // Aliases for compatibility
            'educations' => $this->courses,
            'certificates' => $this->languages,
            'stats' => $this->software,
            'updated_at' => $this->updatedAt,
        ];
    }
}

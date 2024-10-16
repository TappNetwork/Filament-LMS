# Development Reccomendations
- create the directory {project}/packages
- from within the packages directory, clone this repo
- (if necessary) add a type:path repository to project composer.json

# LMS Features
## Frontend LMS Panel
contains the LMS experience for the end user
### Course Library
- user can view courses available to them
- completion status 
### Course UI
- shown when progressing through a single course
- left sidebar showing lessons with steps expanding from them
- icons in sidebar indicating the type of material for each step
- middleware to resume current step
- middleware to prevent skipping steps
### Other Frontend
- profile
- anything else?

## Admin Plugin 
(should these be resource groups in existing panel or its own panel?)
### LMS resource group contains the following resources:
#### Course
- Top level of learning material data structure
- courses do not have an order. they are independant
- courses can be public or invite only
#### Lesson
- Intermediary level data structure
- Has Order (e.g. lesson 1 must be completed before starting lesson 2)
- *in the future* we may want to add support for lessons containing lessons to allow clients more customizability (lesson 1 contains lesson 1.1)
- name optional
#### Step
- Represents a single view in the LMS
- has order
- has material
- name optional
### Material Resource Group
- Video (do we use vimeo or something else?)
- Survey (form for student to fill out)
- Quiz (unlike a survey, a quiz has correct answers and a score)
- Text (Wysiwyg?)
- Image

<?php

return [

    'tabs' => [
        'label' => 'Course editor',
        'details' => 'Details',
        'curriculum' => 'Curriculum',
        'access' => 'Access',
        'save_course_for_lessons' => 'Save the course to add lessons.',
        'save_course_for_access' => 'Save the course to manage access.',
    ],

    'actions' => [
        'courses' => 'Courses',
        'preview' => 'Preview',
        'save_changes' => 'Save changes',
        'save_and_preview' => 'Save & preview',
        'cancel' => 'Cancel',
    ],

    'sections' => [
        'general' => [
            'heading' => 'General',
            'description' => 'Basic course information',
        ],
        'advanced' => [
            'heading' => 'Advanced',
        ],
        'visibility' => [
            'heading' => 'Visibility',
            'description' => 'Who can see this course',
        ],
        'image' => [
            'heading' => 'Image',
        ],
        'credits' => [
            'heading' => 'Credits',
            'description' => 'Credits awarded on completion',
        ],
        'completion' => [
            'heading' => 'Completion',
            'description' => 'How learners complete this course',
        ],
        'test_settings' => [
            'heading' => 'Test settings',
        ],
    ],

    'fields' => [
        'name' => [
            'label' => 'Name',
        ],
        'description' => [
            'label' => 'Description',
        ],
        'slug' => [
            'label' => 'Slug',
            'helper' => 'Auto-generated from name',
            'course_helper' => 'Used for urls.',
        ],
        'external_id' => [
            'label' => 'External ID',
            'helper' => 'Used for external integrations like HubSpot. Updating this will cause a new property to be added to the integration.',
            'regex' => 'External ID must contain only lowercase letters, numbers, and underscores, and must start with a letter.',
            'max' => 'External ID cannot exceed 100 characters.',
        ],
        'image' => [
            'label' => 'Image',
            'helper' => 'Upload a course image.',
        ],
        'is_private' => [
            'label' => 'Private course',
            'helper' => 'Only users explicitly granted access can see and enroll in this course',
        ],
        'completion_mode' => [
            'label' => 'Completion mode',
            'native' => 'Native (per-step in LMS)',
            'scorm12' => 'SCORM 1.2 API sync',
            'html5' => 'HTML5 (started on enter, complete on exit or player signal)',
        ],
        'required_test_percentage' => [
            'label' => 'Required test percentage',
            'helper' => 'Leave empty to accept any passing score',
            'hint' => 'User will not be able to complete the course until they have met this requirement.',
        ],
        'embedded_player' => [
            'label' => 'Embedded player',
            'helper' => 'Play course content inside the LMS embedded player',
            'hint' => 'Hides LMS step sidebar and uses the SCORM/HTML5 package as the primary navigation.',
        ],
        'evaluation_course' => [
            'label' => 'Evaluation course',
            'hint' => 'Set this on the training course and select its evaluation course — not the other way around.',
            'invalid' => 'Choose an evaluation-only course that is not already linked as a training course.',
        ],
        'certificate_template' => [
            'label' => 'Certificate Template',
            'helper' => 'Required. Form must be saved before previewing.',
            'preview' => 'Click to Preview',
        ],
        'credit_category' => [
            'label' => 'Category',
        ],
        'credits' => [
            'label' => 'Credits',
        ],
    ],

    'outline' => [
        'heading' => 'Course outline',
        'description' => 'Drag lessons and steps to reorder. Each step has one material.',
        'empty_heading' => 'No lessons yet',
        'empty_description' => 'Add a lesson to start building this course.',
        'lesson_heading' => 'Lesson :number · :name · :count :steps',
        'step_count' => '{0} steps|{1} step|[2,*] steps',
        'step_heading' => 'Step :number · :name',
        'reorder_lesson' => 'Reorder lesson',
        'reorder_step' => 'Reorder step',
        'edit_lesson' => 'Edit lesson',
        'clone_lesson' => 'Clone lesson',
        'delete_lesson' => 'Delete lesson',
        'edit_step' => 'Edit step',
        'duplicate_step' => 'Duplicate step',
        'delete_step' => 'Delete step',
        'add_step' => 'Add step',
        'add_lesson' => 'Add lesson',
        'optional' => 'Optional',
        'copy_suffix' => 'copy',
        'save_lesson' => 'Save lesson',
        'clone' => 'Clone',
        'clone_description' => 'The lesson and its steps will be copied. Steps keep the same material. The copy is added at the end of the outline, not next to the original.',
        'delete' => 'Delete',
        'delete_lesson_description' => 'This lesson and its steps will be deleted, along with any learner progress on those steps.',
        'delete_step_description' => 'This step will be deleted, along with any learner progress on it.',
        'lesson_added' => 'Lesson added',
        'lesson_saved' => 'Lesson saved',
        'lesson_cloned' => 'Lesson cloned',
        'lesson_cloned_body' => 'The copy is at the end of the outline.',
        'lesson_deleted' => 'Lesson deleted',
        'step_duplicated' => 'Step duplicated',
        'step_duplicated_body' => 'The copy is at the end of the lesson.',
        'step_deleted' => 'Step deleted',
    ],

    'step' => [
        'new_heading' => 'New step',
        'edit_heading' => 'Edit step: :name',
        'default_name' => 'Step :number',
        'save' => 'Save step',
        'saved' => 'Step saved',
        'optional' => 'Optional step',
        'optional_helper' => 'Learners can skip this step',
        'content' => 'Content',
        'content_placeholder' => 'Enter step text content...',
        'content_helper' => 'This text will be displayed at the top of the step page for all users, before the step material. This is useful for providing instructions, context, or additional information about the step.',
        'retry_step' => 'Retry step',
        'retry_step_helper' => 'Learners who fail are sent back to this step',
        'retry_step_hint' => 'If the user does not get all questions correct, they will be shown a message with a link to this step to review before retrying the test.',
        'require_perfect_score' => 'Require perfect score',
        'require_perfect_score_helper' => 'Learner must score 100% to pass',
        'player_slide_id' => 'Player slide ID',
        'player_slide_id_placeholder' => 'e.g. slide_04',
        'player_slide_id_helper' => 'Optional · links this step to a slide in the embedded player',
        'validation' => [
            'retry_step' => 'Choose a step from this course.',
        ],
    ],

    'material' => [
        'label' => 'Material',
        'choose_existing' => 'Choose existing',
        'create_new' => 'Create new',
        'add' => 'Add material',
        'add_heading' => 'Add :type',
        'use' => 'Use material',
        'create_heading' => 'New material',
        'create_description' => 'Fill in the fields for this material. A preview shows here when one is available.',
        'existing_heading' => 'Existing materials',
        'existing_description' => 'The 10 most recent. Search to check whether this material already exists.',
        'search' => 'Search',
        'search_placeholder' => 'Search by name',
        'use_existing' => 'Or use an existing material',
        'existing_helper' => 'Select one to use it instead of creating a new material.',
        'empty' => 'No materials match.',
        'invalid_existing' => 'Choose a material of this type.',
        'preview' => 'Preview',
        'detach' => 'Detach',
        'fields' => [
            'url' => 'URL',
            'file' => 'File',
            'image' => 'Image',
            'preview_image' => 'Custom preview image (optional)',
            'preview_image_helper' => 'If set, this image will be used as the document preview.',
            'link_preview_helper' => 'If not provided, the preview will be generated from the URL.',
            'form' => 'Form',
            'form_helper' => 'For tests, you may only select forms that are locked and no longer open to modification',
        ],
        'none' => 'No material',
        'fallback' => 'Material',
        'test_perfect' => 'Test · 100% required',
        'types' => [
            'video' => 'Video',
            'document' => 'Document',
            'link' => 'Link',
            'image' => 'Image',
            'form' => 'Form',
            'test' => 'Test',
            'library_file' => 'Library file',
            'library_link' => 'Library link',
        ],
    ],

];

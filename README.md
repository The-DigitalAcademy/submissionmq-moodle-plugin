# 🤖 Submission Message Queue

A **Moodle local plugin** that sends assignment and quiz essay submission data to RabbitMQ message queues for asynchronous processing, grading automation, or integration with external systems.

---

## 🚀 What It Does

This plugin listens for two types of Moodle events and, if the corresponding activity is tagged with a configured prefix, sends the relevant data to RabbitMQ queues:

- **Assignment submissions** — fires when a student submits an assignment.
- **Quiz essay submissions** — fires when a student submits a quiz attempt. Any essay-type questions in that attempt are extracted and sent, one message per essay question.

Data sent for assignments includes:

- Online text submissions (if present)
- Assignment details (name, intro, grade, etc.)
- User and course information
- Assignment grading rubric (if configured)

Data sent for quiz essays includes:

- The learner's essay response text
- The question text and grading guide (pulled from the question's own "Information for graders" field in Moodle)
- The maximum mark for the question
- Internal Moodle identifiers (userid, quiz id, attempt id, slot) for audit/traceability

Both flows allow integration with external systems for automatic grading, analytics, or notifications.

---

## ⚙️ Installation

### Prerequisites

- Administrator access to the Moodle instance.
- Moodle 4.x or later (tested against Moodle 5.3dev)
- PHP 8+
- [Composer](https://getcomposer.org/doc/00-intro.md) (for `php-amqplib/php-amqplib`)
- A running [RabbitMQ](https://www.rabbitmq.com/docs/download) instance accessible from the Moodle server
- RabbitMQ credentials (username, password, host, port, vhost)

### Step-by-step Installation

1.  Download the plugin files.
2.  Place the plugin files in the `local/` directory of your Moodle installation.
    The directory name for the plugin files should be `submissionmq`:

```
moodle-root
└── local
    └── submissionmq
         └── classes/
              └── observer.php
              └── helpers/
                   └── rabbitmq_helper.php
                   └── rubric_helper.php
                   └── tag_helper.php
                   └── quiz_helper.php
         └── db/
         └── lang/
         └── README.md
         └── settings.php
         └── version.php
```

> Note: for Moodle 5.x installs using the newer `public/` webroot layout, this still goes under `local/submissionmq` relative to the Moodle root — but double check where Composer's `vendor/` directory actually ends up (it may sit one level above `public/`), since `rabbitmq_helper.php` needs the correct relative path to `vendor/autoload.php`.

**Use the git clone command:**

```
cd <moodle-root>/local
git clone https://github.com/The-DigitalAcademy/submissionmq-moodle-plugin submissionmq
```

4. install Rabbitmq PHP Client Library

```
cd <moodle-root>
composer require php-amqplib/php-amqplib
```

5.  Log in as an administrator to your Moodle site.
6.  Navigate to `Site administration > Notifications`. Moodle will detect the new plugin and prompt you to Install it.
7.  Follow the on-screen instructions to complete the installation.

### Configuration (Site Administration)

After installation, you must configure the autograder service details:

1.  Navigate to `Site Administration > Plugins > Local plugins > Submission Message Queue`
2.  Configure the following settings:

| Setting           | Description                                                                                       |
| ----------------- | --------------------------------------------------------------------------------------------------- |
| **RabbitMQ Host** | Hostname or IP of your RabbitMQ broker (e.g., `localhost` or `192.168.1.10`).                       |
| **RabbitMQ Port** | TCP port to connect to RabbitMQ. Default: `5672`.                                                   |
| **Virtual Host**  | virtual host name. defaults to "/".                                                                 |
| **Exchange Name** | The exchange that messages will be published to. Usually a `fanout` exchange.                       |
| **Username**      | Username for RabbitMQ authentication (e.g., `guest`).                                               |
| **Password**      | Password for RabbitMQ authentication. Hidden in UI.                                                 |
| **Tag Prefix**    | Prefix to filter Moodle tags for submissions to queue (e.g., `mqueue_`). Shared by both assignments and quizzes. |

3.  Click **Save changes**.

> **Note on tags and queue names:** whatever tag you apply to an assignment or quiz is used **as the literal RabbitMQ queue name** — there's no prefix stripping. The Tag Prefix setting is only used to decide *which* tags the plugin should act on (any tag containing that substring). If you want a quiz's messages to land in a specific existing queue (e.g. one your grading worker already consumes from), the tag needs to match that queue's name exactly, while still containing the configured Tag Prefix somewhere in it.

---

## 🧪 Testing and Debugging

To verify the plugin is sending the data correctly, you must enable **Developer Debugging Mode**.

### Activating Developer Debugging

1.  Navigate to **Site administration** $\to$ **Development** $\to$ **Debugging**.
2.  Under **Debug messages**, select the option:

    - **DEVELOPER: extra Moodle debugging messages for developers.**

3.  Check the box for **Display debug messages**.
4.  Click **Save changes**.

This will show detailed error messages if the plugin encounters issues sending messages to RabbitMQ.

### Testing the Plugin — Assignments

1.  Create an assignment and tag it with the configured prefix (e.g., `mqueue_autograde`).
2.  Submit the assignment as a student.
3.  Check RabbitMQ queues for incoming messages.
4.  Review Moodle debugging messages if no messages appear.

### Testing the Plugin — Quiz Essays

1.  Create (or use an existing) quiz containing at least one essay-type question, and tag the quiz activity itself with the configured prefix (e.g., `mqueue_gradingjobs`).
2.  Submit a finished attempt as a student, answering the essay question(s).
3.  Check RabbitMQ queues — you should see one message per essay question in the attempt.
4.  Review Moodle debugging messages if no messages appear. Common causes: the quiz activity isn't tagged, the attempt is a preview attempt (previews are intentionally skipped), or the attempt contains no essay-type questions.

> Essay questions with no grading guide populated in their "Information for graders" field will still be sent, just with an empty `grading_guide` — whatever consumes the queue should handle that case (e.g. flag for human review) rather than assume a guide is always present.

---

## 📦 Payload Structure — Assignments

When an assignment submission is sent, the plugin builds a JSON payload.

|**Key**|**Type**|**Description**
|--|--|--|
|`onlinetextid`|Integer|The ID of the online text submission record (if used).
|`submissionid`|Integer|The ID of the overall assignment submission record.
|`onlinetext`|String|The actual text content of the online submission.
|`userid`|Integer|The ID of the user who made the submission.
|`status`|String|The current status of the submission (e.g., 'submitted').
|`courseid`|Integer|The ID of the course the assignment belongs to.
| `cmid`| Integer| The Course Module ID of the assignment ( id from `course_modules` table).
|`assignmentid`|Integer|The ID of the assignment instance (`mod_assign` table).
|`assignmentname`|String|The name of the assignment.
|`assignmentintro`|String|The introductory text of the assignment.
|`assignmentactivity`|String|The type of assignment activity (e.g., 'assign').
|`assignmentgrade`|Integer|The maximum possible grade for the assignment.
|`timecreated`|Integer|Unix timestamp when the submission was created.
|`assignmentrubric`|Object|The defined rubric criteria and levels for the assignment (if applicable).

Fields may be null if not applicable.

**Example of the assignment payload:**

```json
{
  "onlinetextid": "30",
  "submissionid": "1",
  "onlinetext": "<p>https://github.com/The-DigitalAcademy/moodle-local-autograder-plugin</p>",
  "userid": "2",
  "status": "submitted",
  "courseid": "2",
  "cmid": "23",
  "assignmentid": "1",
  "assignmentname": "Coding Project",
  "assignmentintro": "<p>Project introduction</p>",
  "assignmentactivity": "<p>project instructions: submit a link to your github repo</p>",
  "assignmentgrade": "100",
  "assignmentrubric": {
    "name": "Rubric Name",
    "description": "Rubric Description",
    "criteria": [
      {
        "criterionid": "1",
        "criterion": "documentation",
        "levels": [
          {"id": "1", "definition": "little to no documentation", "score": "0.00000"},
          {"id": "2", "definition": "good documentation", "score": "25.00000"}
        ]
      }
    ]
  }
}
```

---

## 📦 Payload Structure — Quiz Essays

When a quiz attempt is submitted, the plugin sends **one message per essay-type question** in the attempt. The payload shape is a "submission" job structure, designed to match generic grading worker input rather than mirroring the assignment payload's flat shape:

|**Key**|**Type**|**Description**
|--|--|--|
|`course_name`|String|The full name of the course the quiz belongs to.
|`assignment_name`|String|The quiz name plus a question label, e.g. `"Quiz Name — Q1"` (named `assignment_name` for consistency with the wider grading pipeline, not specific to Moodle assignments).
|`subject_area`|String|Currently always empty — no direct Moodle equivalent exists yet.
|`submission.learner_id`|String|The student's Moodle `idnumber`, falling back to their raw Moodle `userid` (as a string) if `idnumber` isn't set.
|`submission.question_text`|String|The essay question's prompt text.
|`submission.learner_response`|String|The learner's typed essay response (HTML).
|`submission.max_grade`|Number|The maximum mark for this question.
|`submission.grading_guide`|String|The question's grading guide, pulled from Moodle's own "Information for graders" field on the essay question (`qtype_essay_options.graderinfo`). May be empty if that field was never populated for the question.
|`submission._moodle_userid`|Integer|The Moodle user ID of the learner.
|`submission._moodle_quiz_id`|Integer|The Moodle quiz ID (`mdl_quiz.id`).
|`submission._moodle_attempt_id`|Integer|The quiz attempt ID (`mdl_quiz_attempts.id`).
|`submission._moodle_slot`|Integer|The question's slot number within the attempt.
|`submission._moodle_question_number`|Integer|A 1-based index of this essay question among the essay questions in the attempt.
|`submission._moodle_objective_score`|Number|Sum of marks already earned on non-essay (auto-graded) questions in the same attempt, so a consumer can add it to the essay total once all essays are graded.

**Example of a quiz essay payload:**

```json
{
  "course_name": "Business Analysis",
  "assignment_name": "Quiz: Implementing a Data Analytics Platform — Q1",
  "subject_area": "",
  "submission": {
    "learner_id": "u12345678",
    "question_text": "<p>Why did you choose these specific stakeholders?</p>",
    "learner_response": "<p>I picked these stakeholders because...</p>",
    "max_grade": 5,
    "grading_guide": "Example Answer: ... Marks Breakdown: 5 points: ... 4 points: ...",
    "_moodle_userid": 79,
    "_moodle_quiz_id": 7,
    "_moodle_attempt_id": 302,
    "_moodle_slot": 1,
    "_moodle_question_number": 1,
    "_moodle_objective_score": 0
  }
}
```

> A quiz with 8 essay questions produces 8 separate messages per attempt — one per question, not one combined message per attempt. Whatever consumes the queue is responsible for grouping messages by `_moodle_attempt_id` if it needs to combine them into a single final grade.
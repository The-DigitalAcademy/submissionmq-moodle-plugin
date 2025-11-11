# 🤖 Submission Message Queue Plugin (`local_submissionmq`)

A **Moodle local plugin** that sends assignment submission data to RabbitMQ message queues for asynchronous processing, grading automation, or integration with external systems.

---

## 🚀 What It Does

This plugin listens for assignment submission events and, if the submission is tagged with a configured prefix, sends the relevant data to RabbitMQ queues. The data includes:

- Online text submissions (if present)
- Assignment details (name, intro, grade, etc.)
- User and course information
- Assignment grading rubric (if configured)

It allows integration with external systems for automatic grading, analytics, or notifications.

---

## ⚙️ Installation

### Prerequisites

- Administrator access to the Moodle instance.
- Moodle 4.x or later
- PHP 8+
- [Composer](https://getcomposer.org/doc/00-intro.md) (for `php-amqplib/php-amqplib`)
- A running [RabbitMQ](https://www.rabbitmq.com/docs/download) instance accessible from the Moodle server
- RabbitMQ credentials (username, password, host, port)

### Step-by-step Installation

1.  Download the plugin files.
2.  Place the plugin in the `local/` directory of your Moodle installation:

```
moodle/
└── local/
└── submissionmq/
```

3.  Place the plugin files inside the new directory.
4.  Log in as an administrator to your Moodle site.
5.  Navigate to `Site administration > Notifications`. Moodle will detect the new plugin and prompt you to Install it.
6.  Follow the on-screen instructions to complete the installation.

### Configuration (Site Administration)

After installation, you must configure the autograder service details:

1.  Navigate to `Site Administration > Plugins > Local plugins > Submission Message Queue`
2.  Configure the following settings:

| Setting           | Description                                                                         |
| ----------------- | ----------------------------------------------------------------------------------- |
| **RabbitMQ Host** | Hostname or IP of your RabbitMQ broker (e.g., `localhost` or `192.168.1.10`).       |
| **RabbitMQ Port** | TCP port to connect to RabbitMQ. Default: `5672`.                                   |
| **Exchange Name** | The exchange that messages will be published to. Usually a `fanout` exchange.       |
| **Username**      | Username for RabbitMQ authentication (e.g., `guest`).                               |
| **Password**      | Password for RabbitMQ authentication. Hidden in UI.                                 |
| **Tag Prefix**    | Prefix to filter Moodle assignment tags for submissions to queue (e.g., `mqueue_`). |

3.  Click **Save changes**.

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

### Testing the Plugin

1.  Create an assignment and tag it with the configured prefix (e.g., mqueue_autograde).
2.  Submit the assignment as a student.
3.  Check RabbitMQ queues for incoming messages.
4.  Review Moodle debugging messages if no messages appear.

---

## 📦 Payload Structure

The plugin sends a **JSON** object in the body of the POST request to the autograder service.
|**Key**|**Type**|**Description**
|--|--|--|
|`onlinetextid`|Integer|The ID of the online text submission record (if used).
|`submissionid`|Integer|The ID of the overall assignment submission record.
|`onlinetext`|String|The actual text content of the online submission.
|`userid`|Integer|The ID of the user who made the submission.
|`status`|String|The current status of the submission (e.g., 'submitted').
|`courseid`|Integer|The ID of the course the assignment belongs to.
|`assignmentid`|Integer|The ID of the assignment instance (`mod_assign` table).
|`assignmentname`|String|The name of the assignment.
|`assignmentintro`|String|The introductory text of the assignment.
|`assignmentactivity`|String|The type of assignment activity (e.g., 'assign').
|`assignmentgrade`|Integer|The maximum possible grade for the assignment.
|`timecreated`|Integer|Unix timestamp when the submission was created.
|`assignmentrubric`|Object|The defined rubric criteria and levels for the assignment (if applicable).

**Example of the payload:**

JSON

```
{
  "onlinetextid": "30",
  "submissionid": "1",
  "onlinetext": "<p>https://github.com/The-DigitalAcademy/moodle-local-autograder-plugin</p>",
  "userid": "2",
  "status": "submitted",
  "courseid": "2",
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
      },
      // ... more criteria
    ]
  }
}
```

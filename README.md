# 🤖 Moodle Local Autograder Plugin (`local_autograder`)

A Moodle local plugin that automatically sends assignment submission data to a configured external autograder service upon submission, provided the assignment activity is tagged with 'autograde'.

---

## 🚀 What It Does

The `local_autograder` plugin observes the Moodle event `\mod_assign\event\assessable_submitted`. When a student submits an assignment:

1.  It **checks if the assignment activity has the tag 'autograde'**. If not, the process is skipped silently.
2.  If the 'autograde' tag is present, it **compiles the submission, user, assignment, and rubric data** into a JSON payload.
3.  It **sends this JSON payload** via an HTTP POST request to a **configurable external API endpoint** (your autograder service). An optional **API Key** can be included in the headers for authentication.
4.  It **logs the success or failure** of the API call using Moodle's debugging system.

This allows your external autograder service to receive the necessary data to process the submission and provide feedback or grades back to Moodle (via a separate mechanism).

---

## ⚙️ Installation

### Prerequisites

- A Moodle instance (requires Moodle version **4.0** or later, based on `version.php`).
- Administrator access to the Moodle instance.

### Step-by-step Installation

1.  **Download** the plugin files.
2.  **Create a new directory** named `autograder` inside the Moodle local folder:

    Bash

    ```
    moodle/local/

    ```

3.  **Place the plugin files** inside the new directory.
4.  **Log in as an administrator** to your Moodle site.
5.  Navigate to **Site administration** $\to$ **Notifications**. Moodle will detect the new plugin and prompt you to **Install** it.
6.  Follow the on-screen instructions to complete the installation.

### Configuration (Site Administration)

After installation, you **must** configure the autograder service details:

1.  Navigate to **Site administration** $\to$ **Plugins** $\to$ **Local plugins**.
2.  Click on the plugin link: **Local Autograder Connector**.
3.  Configure the following settings:

    - **Autograder API Endpoint:** Enter the full URL where assignment submission payloads should be POSTed (e.g., `https://your-autograder.com/api/submission`).
    - **Autograder API Key/Secret (Optional):** Enter a key or secret. This is sent in the request header (`X-API-Key`) for authentication with the external service.

4.  Click **Save changes**.

---

## 🧪 Testing and Debugging

To verify the plugin is sending the data correctly, you must enable **Developer Debugging Mode**.

### Activating Developer Debugging

1.  Navigate to **Site administration** $\to$ **Development** $\to$ **Debugging**.
2.  Under **Debug messages**, select the option:

    - **DEVELOPER: extra Moodle debugging messages for developers.**

3.  Check the box for **Display debug messages**.
4.  Click **Save changes**.

### Testing the Plugin

1.  Create or find an **Assignment** activity.
2.  **Edit the assignment settings** and under the **Tags** section, add the tag **`autograde`**.
3.  Log in as a student and **submit** the assignment.
4.  The submission action will trigger the plugin. If the API call is successful, you will see a message on the screen (or in the Moodle error log) similar to:

    > `✅ Successfully sent submission for user [user_id] to external API.`

5.  If it fails, you will see:

    > `❌ Failed to send submission. HTTP Code: [code], Response: [response]`

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

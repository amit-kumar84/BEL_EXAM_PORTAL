📋 BULK UPLOAD FORMATS - BEL Kotdwar Exam Portal
================================================

This folder contains CSV templates for bulk uploading students and questions.

📂 FILES INCLUDED:
─────────────────

1️⃣ STUDENTS
   └─ students_bulk_format.csv
      Format: name, email, roll_number, dob, category, password
      Where:
        • name: Full name of student
        • email: Valid email address (must be unique)
        • roll_number: Roll/Admission number (must be unique)
        • dob: Date of birth in YYYY-MM-DD format
        • category: internal or external
        • password: (Optional) Login password. If empty, auto-generated

2️⃣ QUESTIONS - SINGLE TYPE FORMATS
   All use format: question_type,question,option1,option2,option3,option4,correct,marks,negative

   A) questions_mcq_format.csv
      └─ Multiple Choice (MCQ)
         • Exactly ONE correct answer
         • correct: Number 1-4 (position of correct option)
         • Example: 2 means option2 is correct
         • marks: Points for correct answer
         • negative: Points deducted for wrong answer

   B) questions_multiselect_format.csv
      └─ Multiple Select
         • MULTIPLE correct answers possible
         • correct: Pipe-separated option numbers (e.g., 1|3|4)
         • Example: "1|3" means options 1 and 3 are both correct
         • marks: Points for correct answer
         • negative: Points deducted for wrong answer

   C) questions_true_false_format.csv
      └─ True/False
         • Only 2 options: True or False
         • correct: true or false (lowercase)
         • option1: True, option2: False (always)
         • option3, option4: Leave empty

   D) questions_short_answer_format.csv
      └─ Short Answer
         • Text-based answer (manual grading)
         • correct: Expected answer text
         • option1, option2, option3, option4: Leave empty
         • marks: Points for correct answer
         • negative: Usually 0 (manual grading)

   E) questions_numeric_format.csv
      └─ Numeric Answer
         • Mathematical answer (auto-graded)
         • correct: The numeric value
         • option1, option2, option3, option4: Leave empty
         • Example: 40, 25, 12, 13, etc.

3️⃣ MIXED QUESTIONS
   └─ questions_mixed_format.csv
      Contains ALL question types in ONE file:
      • MCQ, Multi-Select, True/False, Short Answer, Numeric
      • question_type column determines how to interpret the row
      • Great for comprehensive exams with varied question types


🚀 HOW TO USE:
──────────────

STUDENTS:
  1. Go to Admin Panel > Students
  2. Click "Bulk Upload Students" button
  3. Copy data from students_bulk_format.csv or paste CSV content
  4. (Optional) Select exams to assign
  5. Click Upload

QUESTIONS:
  1. Go to Admin Panel > Exams > Edit > Questions
  2. Click "Bulk Upload Questions" button
  3. Select exam (questions will be added to this exam)
  4. Copy data from relevant CSV file (mcq, multiselect, true_false, short_answer, numeric, or mixed)
  5. Click Upload


📝 IMPORTANT NOTES:
──────────────────

CSV FORMAT:
  • Use comma (,) as delimiter
  • Keep headers in first row
  • Empty quotes "" for empty fields
  • Do NOT include column headers in data rows
  • For options with commas, wrap in quotes: "Option, with comma"

DATES:
  • DOB format: YYYY-MM-DD
  • Example: 1999-05-15

CORRECT ANSWERS:
  • MCQ: Single number 1-4
  • Multi-Select: Numbers separated by pipe | (e.g., 1|3|4)
  • True/False: "true" or "false" (lowercase)
  • Short Answer: Any text
  • Numeric: Number (can be decimal)

MARKS & NEGATIVE:
  • marks: Points awarded for correct answer (can be decimal)
  • negative: Points deducted for wrong answer (can be decimal)
  • Set negative=0 for short answer (manual grading)

DUPLICATES:
  • Email must be unique per student
  • Roll number must be unique per student
  • Question text can be repeated (different questions)

ERROR HANDLING:
  • Check for required fields (name, email, roll_number for students)
  • Ensure dates are in YYYY-MM-DD format
  • Verify correct answer values match the format
  • Invalid rows will be reported with line numbers


💡 EXAMPLES:
────────────

MCQ Correct Answer:
  Question: "Capital of India?"
  Option1: Mumbai
  Option2: Delhi (CORRECT)
  Option3: Bangalore
  Option4: Chennai
  Correct: 2

Multi-Select Correct Answer:
  Question: "Programming languages? (Select all)"
  Option1: Python (CORRECT)
  Option2: Table
  Option3: Java (CORRECT)
  Option4: Chair
  Correct: 1|3

Numeric Answer:
  Question: "What is 25 + 15?"
  Correct: 40


📞 SUPPORT:
───────────
For issues with bulk upload, ensure:
  • CSV format is correct (UTF-8 encoding)
  • No special characters in sensitive fields
  • All required fields are filled
  • Date formats are correct (YYYY-MM-DD)

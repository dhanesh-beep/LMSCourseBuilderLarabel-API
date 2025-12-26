import json
import os

def load_course_details():
    """
    Dynamically load the course details text from the JSON file.
    Returns the raw text string from the 'course_details' key.
    """
    json_file_path = 'IT_policy_Course_cleaned.json'

    # Check if file exists
    if not os.path.exists(json_file_path):
        raise FileNotFoundError(f"JSON file not found: {json_file_path}")

    # Load and parse JSON
    with open(json_file_path, 'r', encoding='utf-8') as file:
        data = json.load(file)

    # Extract the course_details text
    if 'course_details' not in data:
        raise KeyError("Key 'course_details' not found in JSON file")

    raw_text = data['course_details']

    return raw_text

# Example usage - dynamically load the text
if __name__ == "__main__":
    try:
        course_text = load_course_details()
        print(f"✅ Successfully loaded course details text")
        print(f"Text length: {len(course_text)} characters")
        print(f"\nFirst 200 characters:\n{course_text[:200]}...")
        print(f"\nLast 200 characters:\n...{course_text[-200:]}")

        # The raw_text is now available as a dynamic string variable
        raw_text = course_text
        print(f"\nRaw text variable created with {len(raw_text)} characters")

    except Exception as e:
        print(f"❌ Error loading course details: {e}")
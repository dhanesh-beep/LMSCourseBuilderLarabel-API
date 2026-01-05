import json

def remove_ids(obj):
    """Recursively remove course_id, lesson_id, section_id, and row_id from all objects"""
    if isinstance(obj, dict):
        # Remove the specified keys if they exist
        keys_to_remove = ['course_id', 'lesson_id', 'section_id', 'row_id']
        for key in keys_to_remove:
            if key in obj:
                del obj[key]
        
        # Recursively process all values
        for value in obj.values():
            remove_ids(value)
    elif isinstance(obj, list):
        # Recursively process all items in the list
        for item in obj:
            remove_ids(item)

# Read the JSON file
with open('VALID_COURSE.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

# Remove the specified ID keys
remove_ids(data)

# Write the updated JSON back
with open('VALID_COURSE.json', 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=4, ensure_ascii=False)

print("Removed 'course_id', 'lesson_id', 'section_id', and 'row_id' keys from all objects!")


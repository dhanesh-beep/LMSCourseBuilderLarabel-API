import json

def add_is_published(obj):
    """Add is_published: true to each lesson and section"""
    if isinstance(obj, dict):
        # Check if this is a lesson (has 'lesson_order' key)
        if 'lesson_order' in obj:
            obj['is_published'] = True
        
        # Check if this is a section (has 'section_order' key)
        if 'section_order' in obj:
            obj['is_published'] = True
        
        # Recursively process all values
        for value in obj.values():
            add_is_published(value)
    elif isinstance(obj, list):
        # Recursively process all items in the list
        for item in obj:
            add_is_published(item)

# Read the JSON file
with open('VALID_COURSE.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

# Add is_published to all lessons and sections
add_is_published(data)

# Write the updated JSON back
with open('VALID_COURSE.json', 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=4, ensure_ascii=False)

print("Added 'is_published': true to all lessons and sections!")


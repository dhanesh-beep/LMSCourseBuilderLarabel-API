import json

def generate_content_for_lesson(lesson_title):
    """Generate relevant educational content based on lesson title"""
    # Extract the main topic from lesson title (e.g., "Lesson 1 : Lesson One" -> "Lesson One")
    if ':' in lesson_title:
        topic = lesson_title.split(':')[1].strip()
    else:
        topic = lesson_title
    
    # Generate relevant content based on the topic
    content_templates = {
        "Lesson One": "This lesson covers the fundamental concepts and principles. You will learn the basics that form the foundation for more advanced topics. Understanding these core concepts is essential for your continued learning journey.",
        "Lesson Two": "In this lesson, we build upon the foundational knowledge from the previous lesson. You will explore intermediate concepts and practical applications that will help you understand how these principles work in real-world scenarios.",
        "Lesson Three": "This lesson delves deeper into advanced topics and complex scenarios. You will learn about sophisticated techniques and strategies that will enhance your understanding and practical skills.",
        "Lesson Four": "This lesson focuses on advanced applications and specialized knowledge. You will explore expert-level concepts and learn how to apply them effectively in various contexts.",
        "Lesson Five": "This lesson covers expert-level topics and mastery concepts. You will learn advanced techniques and strategies that will help you achieve a comprehensive understanding of the subject matter."
    }
    
    # Try to find a matching template
    for key, template in content_templates.items():
        if key.lower() in topic.lower():
            return template
    
    # Default content based on lesson number or topic
    if "one" in topic.lower() or "1" in topic:
        return "This lesson introduces the fundamental concepts and basic principles. You will learn the essential building blocks that are crucial for understanding more complex topics later in the course."
    elif "two" in topic.lower() or "2" in topic:
        return "Building on the foundation from the previous lesson, this section explores intermediate concepts and their practical applications. You will gain hands-on experience with key techniques."
    elif "three" in topic.lower() or "3" in topic:
        return "This lesson covers advanced topics and complex scenarios. You will learn sophisticated approaches and strategies that enhance your practical skills and theoretical understanding."
    elif "four" in topic.lower() or "4" in topic:
        return "This lesson focuses on specialized knowledge and expert-level applications. You will explore advanced techniques that demonstrate mastery of the subject matter."
    elif "five" in topic.lower() or "5" in topic:
        return "This lesson represents the culmination of your learning journey, covering expert-level concepts and mastery techniques. You will develop a comprehensive understanding of all aspects of the topic."
    else:
        # Generic content based on topic name
        return f"This lesson provides comprehensive coverage of {topic}. You will learn essential concepts, practical applications, and gain valuable insights that will enhance your understanding and skills in this area."

def add_content_to_text_widgets(obj, current_lesson_title=None):
    """Recursively add content to widgets with type 'text'"""
    if isinstance(obj, dict):
        # Track the current lesson title
        if 'title' in obj and 'lesson_order' in obj:
            current_lesson_title = obj['title']
        
        # Check if this is a widget with type 'text'
        if 'type' in obj and obj['type'] == 'text' and 'content' not in obj:
            if current_lesson_title:
                obj['content'] = generate_content_for_lesson(current_lesson_title)
            else:
                obj['content'] = "This lesson provides essential knowledge and practical insights to help you understand the key concepts and apply them effectively."
        
        # Recursively process all values
        for value in obj.values():
            add_content_to_text_widgets(value, current_lesson_title)
    elif isinstance(obj, list):
        # Recursively process all items in the list
        for item in obj:
            add_content_to_text_widgets(item, current_lesson_title)

# Read the JSON file
with open('VALID_COURSE.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

# Add content to all text widgets
add_content_to_text_widgets(data)

# Write the updated JSON back
with open('VALID_COURSE.json', 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=4, ensure_ascii=False)

print("Added 'content' key to all widgets with type 'text'!")


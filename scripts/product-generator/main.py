import random
import pandas as pd

def generate_product_name_url(product_type, category):
    base_names = ["Eco", "Lux", "Prime", "Ultra", "Max", "Pro", "Super"]
    name = f"{random.choice(base_names)} {product_type} for {category}"
    url = f"https://{name.replace(' ', '-').lower()}.example.com"
    return name, url

# Only cleaning services related to products
product_types = ["Detergent", "Soap", "Sponge", "Brush", "Mop", "Broom", "Vacuum", "Duster", "Polish", "Wax", "Gloves", "Scrub", "Wipe", "Cleaner", "Disinfectant", "Deodorizer", "Sanitizer", "Air Freshener", "Stain Remover", "Stain Cleaner", "Stain Eraser", "Stain Eliminator", "Stain Destroyer", "Stain Terminator", "Stain Assassin", "Stain Slayer", "Stain Warrior", "Stain Fighter", "Stain Ninja", "Stain Wizard", "Stain Master", "Stain Expert", "Stain Specialist", "Stain Doctor", "Stain Surgeon", "Stain Medic", "Stain Healer", "Stain Savior", "Stain Guardian", "Stain Protector", "Stain Defender", "Stain Shield", "Stain Blocker", "Stain Stopper", "Stain Preventer", "Stain Repellent", "Stain Resistant", "Stain Proof", "Stain Free", "Stainless", "Stain Away", "Stain Gone", "Stain Vanish", "Stain Disappear", "Stain Evaporate", "Stain Eradicate", "Stain Annihilate", "Stain Obliterate", "Stain Exterminate", "Stain Erase", "Stain Delete", "Stain Remove", "Stain Eliminate", "Stain Destroy", "Stain Terminate", "Stain Kill", "Stain Wipeout", "Stain Purge", "Stain Cleanse", "Stain Purify", "Stain Sanitize", "Stain Disinfect", "Stain Deodorize", "Stain Freshen", "Stain Refresh", "Stain Renew", "Stain Restore", "Stain Revive", "Stain Rejuvenate", "Stain Regenerate", "Stain Recharge", "Stain Replenish", "Stain Reinvigorate", "Stain Reenergize", "Stain Reawaken", "Stain Reanimate", "Stain Resurrect", "Stain Reborn", "Stain Reborn", "Stain Reborn", "Stain Reborn", "Stain"]
# Only cleaning services related categories
categories = ["Kitchen", "Bathroom", "Living Room", "Bedroom", "Dining Room", "Home Office", "Laundry Room", "Garage", "Basement", "Attic", "Patio", "Deck", "Yard", "Garden", "Driveway", "Walkway", "Sidewalk", "Porch", "Balcony", "Stairs", "Hallway", "Closet", "Cabinet", "Shelf", "Drawer", "Counter", "Table", "Chair", "Sofa", "Couch", "Ottoman", "Bench", "Stool", "Desk", "Bookcase", "Cupboard", "Wardrobe", "Armoire", "Hutch", "Buffet", "Sideboard", "Credenza", "Console", "Vanity", "Nightstand", "Dresser", "Chest", "Trunk", "Mirror", "Clock", "Lamp", "Light", "Chandelier", "Sconce", "Pendant", "Ceiling", "Fan", "Vent", "Window", "Shade", "Blind", "Curtain", "Drape", "Valance", "Rod", "Tieback", "Tassel", "Cushion", "Pillow", "Throw", "Blanket", "Quilt", "Comforter", "Duvet", "Sheet", "Cover", "Sham", "Skirt", "Bedspread", "Bedskirt", "Mattress", "Boxspring", "Frame", "Headboard", "Footboard", "Rails", "Post", "Canopy", "Trundle", "Daybed", "Bunk", "Loft", "Futon", "Sleigh", "Murphy", "Waterbed"]

product_data_batch = {
    "Name": [],
    "URL": [],
    "Category": [],
    "Price": []
}

# Generate 1000000 mock products
for _ in range(1000000):
    product_type = random.choice(product_types)
    category = random.choice(categories)
    name, url = generate_product_name_url(product_type, category)
    price = round(random.uniform(5.99, 99.99), 2)
    
    product_data_batch["Name"].append(name)
    product_data_batch["URL"].append(url)
    product_data_batch["Category"].append(category)
    product_data_batch["Price"].append(price)

# Convert to DataFrame
product_batch_df = pd.DataFrame(product_data_batch)

# Save to CSV
product_batch_df.to_csv("mock-products.csv", index=False)

# Print first 5 rows
print(product_batch_df.head())

print("Done!")
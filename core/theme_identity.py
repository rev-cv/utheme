import random
from urllib.parse import urlparse

_FAMILIES = [
    # trees & woody plants
    "Falcon",   "Granite",  "Willow",   "Cobalt",   "Cedar",
    "Frost",    "Hawk",     "Copper",   "Birch",    "Storm",
    "Jade",     "Hazel",    "Crimson",  "Maple",    "Slate",
    "Robin",    "Azure",    "Timber",   "Kestrel",  "Russet",
    "Cinder",   "Heron",    "Ivory",    "Larch",    "Thistle",
    "Oak",      "Pine",     "Ash",      "Elm",      "Beech",
    "Rowan",    "Yew",      "Spruce",   "Alder",    "Aspen",
    "Walnut",   "Chestnut", "Sycamore", "Hawthorn", "Elder",
    "Juniper",  "Laurel",   "Holly",    "Poplar",   "Linden",
    "Mulberry", "Briar",    "Gorse",    "Fern",     "Heather",
    # birds
    "Wren",     "Merlin",   "Osprey",   "Raven",    "Finch",
    "Swift",    "Harrier",  "Martin",   "Plover",   "Curlew",
    # minerals & colours
    "Amber",    "Obsidian", "Onyx",     "Garnet",   "Topaz",
    "Opal",     "Sienna",   "Pewter",   "Flint",    "Basalt",
    "Quartz",   "Sable",    "Teal",     "Indigo",   "Umber",
    "Tawny",    "Ochre",    "Vermeil",  "Moor",     "Heath",
    # landscape & weather
    "Cliff",    "Bluff",    "Knoll",    "Mist",     "Dawn",
    "Ember",    "Dusk",     "Dune",     "Drift",    "Crag",
    "Brae",     "Fen",      "Cove",     "Sorrel",   "Yarrow",
    "Tansy",    "Sedge",    "Whin",     "Reed",     "Cobble",
]

_SUFFIXES = [
    # classic English toponym endings
    "wood",     "field",    "gate",     "brook",    "vale",
    "mere",     "haven",    "dale",     "stone",    "ford",
    "path",     "view",     "crest",    "ridge",    "hill",
    "side",     "burn",     "wick",     "port",     "bank",
    "glen",     "holt",     "shaw",     "lea",      "ton",
    "fell",     "tor",      "dene",     "bay",      "ley",
    "holm",     "bridge",   "well",     "mead",     "worth",
    "croft",    "ham",      "bury",     "cross",    "pool",
    "spring",   "creek",    "pass",     "stow",     "ness",
    "thorp",    "garth",    "beck",     "gill",     "tarn",
    # terrain & water features
    "scar",     "rigg",     "brow",     "clough",   "stead",
    "stoke",    "fleet",    "marsh",    "down",     "mount",
    "edge",     "grove",    "hollow",   "rise",     "head",
    "top",      "run",      "reach",    "bend",     "point",
    "park",     "green",    "lane",     "court",    "close",
    "yard",     "fold",     "way",      "shore",    "slope",
    "terrace",  "heights",  "falls",    "crossing", "landing",
    "rock",     "trace",    "trail",    "track",    "end",
    "seat",     "sound",    "weir",     "lode",     "rake",
    "nook",     "bight",    "coombe",   "harbor",   "cape",
]

_COMP_FIRST = [
    "Pixel",    "Bright",   "Nova",     "Web",      "Swift",
    "Core",     "Edge",     "Bold",     "Peak",     "Forge",
    "Grid",     "Hub",      "Lumen",    "Mint",     "Nord",
    "Open",     "Pure",     "Quick",    "Root",     "Sky",
    "Tide",     "Ultra",    "Vox",      "Wave",     "Xen",
    "Apex",     "Arc",      "Ark",      "Atom",     "Beam",
    "Blaze",    "Block",    "Blue",     "Byte",     "Cast",
    "Chain",    "Clear",    "Cloud",    "Craft",    "Dash",
    "Data",     "Dawn",     "Deep",     "Dot",      "Drive",
    "Drop",     "Flux",     "Gem",      "Glide",    "Glow",
    "Gold",     "Green",    "Grip",     "Guard",    "Hash",
    "High",     "Hook",     "Icon",     "Idea",     "Ion",
    "Jump",     "Keen",     "Key",      "Kit",      "Knot",
    "Lab",      "Launch",   "Lead",     "Lean",     "Link",
    "Live",     "Load",     "Lock",     "Logic",    "Loop",
    "Mark",     "Match",    "Merge",    "Mesh",     "Meta",
    "Mode",     "Move",     "Net",      "Next",     "Node",
    "Omni",     "Orb",      "Pack",     "Path",     "Ping",
    "Pipe",     "Port",     "Pulse",    "Rack",     "Rail",
    "Ramp",     "Rank",     "Reel",     "Reef",     "Relay",
]

_COMP_SECOND = [
    "Studio",   "Labs",     "Dev",      "Co",       "Works",
    "Digital",  "Media",    "Agency",   "Creative", "Interactive",
    "Solutions","Systems",  "Code",     "Tech",     "Ventures",
    "Hub",      "Network",  "Group",    "HQ",       "Craft",
    "Design",   "Build",    "Forge",    "Space",    "House",
    "Team",     "Partners", "Inc",      "Cloud",    "Apps",
    "Platform", "Software", "Services", "Workshop", "Academy",
    "Guild",    "Collective","Enterprise","Division","Zone",
    "Portal",   "Engine",   "Stack",    "Suite",    "Bundle",
    "Base",     "Lab",      "Projects", "Think",    "Make",
    "Create",   "Launch",   "Hive",     "Nest",     "Den",
    "Loft",     "Shop",     "Store",    "Market",   "Bench",
    "Office",   "Room",     "Global",   "Local",    "Online",
    "Pro",      "Plus",     "Prime",    "Express",  "Smart",
    "Central",  "Depot",    "Foundry",  "Press",    "Signal",
    "Beacon",   "Spark",    "Flare",    "Reach",    "Connect",
    "Bridge",   "Circle",   "Sphere",   "Cube",     "Spot",
    "Tag",      "Label",    "Sign",     "Logo",     "Brand",
    "Artisan",  "Atelier",  "Fleet",    "Yard",     "Deck",
    "Dock",     "Bay",      "Shed",     "Barn",     "Vault",
]


def get_theme_identity(site_url: str) -> dict:
    """Return {name, slug, author} seeded by domain — same domain always yields the same identity."""
    domain = urlparse(site_url).netloc or site_url
    rng    = random.Random(domain)
    name   = rng.choice(_FAMILIES) + rng.choice(_SUFFIXES)
    author = rng.choice(_COMP_FIRST) + " " + rng.choice(_COMP_SECOND)
    return {"name": name, "slug": name.lower(), "author": author}

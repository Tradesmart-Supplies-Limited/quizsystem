<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\QuestionCategory;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    /**
     * Format: [category, type, difficulty, question, correct answer, explanation, points, options (prefix * = correct), flags]
     * flags: b = bonus, d = danger
     */
    public const QUESTIONS = [
        // ---------------- Warehouse
        ['Warehouse', 'multiple_choice', 'easy', 'What does FIFO stand for in stock rotation?', 'First In, First Out', 'The oldest stock is issued first, reducing expiry and obsolescence.', 10, ['*First In, First Out', 'Fast In, Fast Out', 'First Item, Final Order', 'Free In, Free Out']],
        ['Warehouse', 'multiple_choice', 'medium', 'Which stock rotation method is most important for products with expiry dates?', 'FEFO – First Expired, First Out', 'FEFO issues items by expiry date, even if a later batch arrived first.', 10, ['FIFO', 'LIFO', '*FEFO', 'HIFO']],
        ['Warehouse', 'true_false', 'easy', 'Pallets may be stacked higher than the rack label limit if the shelf still looks stable.', 'False', 'Never exceed the rated load or height shown on the racking label.', 10, []],
        ['Warehouse', 'short_answer', 'medium', 'What is the name for a physical count of a small group of stock items done on a rotating schedule instead of all at once?', 'Cycle counting', 'Cycle counts keep records accurate without shutting the warehouse for a full stock-take.', 10, []],
        ['Warehouse', 'multiple_choice', 'medium', 'In ABC analysis, “A” items are usually…', 'High-value items making up a small share of SKUs', 'A items typically represent ~20% of SKUs but ~80% of value, so they get the tightest control.', 10, ['*High-value items making up a small share of SKUs', 'The cheapest, fastest-moving items', 'Items stored in aisle A', 'Items awaiting quality inspection']],
        ['Warehouse', 'scenario', 'hard', 'A delivery arrives with 48 cartons on the paperwork but you count 46. The driver is in a hurry. What should you do?', 'Record the shortage on the delivery note (signed by the driver), receive only what arrived, and notify Procurement.', 'Never sign for goods you did not receive – the discrepancy must be documented before the driver leaves.', 20, [], 'd'],
        ['Warehouse', 'multiple_choice', 'easy', 'What is a “SKU”?', 'Stock Keeping Unit', 'A SKU is a unique code for each distinct product variant held in stock.', 10, ['Standard Kilogram Unit', '*Stock Keeping Unit', 'Supplier Key Uniform', 'Storage Keeping Utility']],
        ['Warehouse', 'true_false', 'medium', 'Dead stock is inventory that has not moved for a long period and is unlikely to sell.', 'True', 'Dead stock ties up cash and space; it should be reviewed regularly.', 10, []],
        // ---------------- Procurement
        ['Procurement', 'multiple_choice', 'easy', 'What document formally authorises a supplier to deliver goods at agreed prices?', 'Purchase Order (PO)', 'The PO is the buyer’s official commitment; it is matched later to the delivery note and invoice.', 10, ['Delivery note', '*Purchase Order', 'Credit note', 'Quotation']],
        ['Procurement', 'short_answer', 'hard', 'What is the “three-way match” used before paying a supplier invoice?', 'Matching the Purchase Order, Goods Received Note and Supplier Invoice', 'Payment is approved only when quantities and prices agree across all three documents.', 15, [], 'b'],
        ['Procurement', 'multiple_choice', 'medium', 'Why do we request at least three quotations for larger purchases?', 'To get competitive pricing and ensure fair, transparent supplier selection', 'Competitive quotes demonstrate value for money and reduce the risk of favouritism.', 10, ['It is required by the supplier', '*Competitive pricing and transparent selection', 'To delay the purchase', 'To increase the order quantity']],
        ['Procurement', 'true_false', 'easy', 'It is acceptable to split a large purchase into several small orders to avoid the approval limit.', 'False', 'Order splitting bypasses controls and breaches procurement policy.', 10, [], 'd'],
        ['Procurement', 'multiple_choice', 'medium', 'What does “lead time” mean?', 'The time between placing an order and receiving the goods', 'Knowing lead times lets us set reorder points so we never run out.', 10, ['Time a manager takes to approve', '*Time between ordering and receiving', 'Time goods spend on the shelf', 'Time to unload a truck']],
        ['Procurement', 'short_answer', 'medium', 'What do we call the stock level at which a new order should be placed?', 'Reorder point (ROP)', 'Reorder point = average daily usage × lead time + safety stock.', 10, []],
        ['Procurement', 'scenario', 'hard', 'A supplier offers you a personal gift to “speed up” approval of their tender. What do you do?', 'Politely decline, do not influence the tender, and report it to your manager / compliance as per the gifts policy.', 'Accepting creates a conflict of interest and may amount to bribery.', 20, [], 'd'],
        // ---------------- Logistics
        ['Logistics', 'multiple_choice', 'easy', 'What is a POD in deliveries?', 'Proof of Delivery', 'The signed POD confirms the customer received the goods in good condition.', 10, ['*Proof of Delivery', 'Point of Dispatch', 'Packing Order Document', 'Payment on Demand']],
        ['Logistics', 'multiple_choice', 'medium', 'Which document lists every item packed in a consignment?', 'Packing list', 'The packing list helps the receiver check contents quickly.', 10, ['Invoice', '*Packing list', 'Purchase requisition', 'Credit note']],
        ['Logistics', 'true_false', 'easy', 'Route planning can reduce fuel costs and improve on-time delivery.', 'True', 'Optimised routes cut distance, idle time and missed delivery windows.', 10, []],
        ['Logistics', 'short_answer', 'medium', 'What is the term for the final leg of delivery from a hub to the customer’s door?', 'Last-mile delivery', 'The last mile is often the most expensive and visible part of delivery.', 10, []],
        ['Logistics', 'multiple_choice', 'hard', 'What does OTIF measure?', 'On Time In Full – orders delivered on the promised date with the complete quantity', 'OTIF is a key customer-facing logistics KPI.', 15, ['Orders Taken In Fridays', '*On Time In Full', 'Order Tracking Information File', 'Outbound Transport Insurance Fee'], 'b'],
        ['Logistics', 'scenario', 'medium', 'A vehicle breaks down with urgent customer orders on board. What are your first three actions?', 'Ensure driver safety, inform dispatch/the customer with a new ETA, arrange a replacement vehicle or transfer.', 'Safety first, then communication, then recovery.', 15, []],
        // ---------------- Customer Service
        ['Customer Service', 'multiple_choice', 'easy', 'A customer is angry about a late delivery. What is the best first step?', 'Listen actively and acknowledge their frustration', 'Customers calm down when they feel heard; solutions come after empathy.', 10, ['Explain it was not your fault', '*Listen and acknowledge their frustration', 'Transfer the call immediately', 'Offer a discount straight away']],
        ['Customer Service', 'true_false', 'easy', 'It is better to promise a fast fix and miss it than to give a realistic timeframe.', 'False', 'Under-promise and over-deliver: broken promises damage trust.', 10, []],
        ['Customer Service', 'short_answer', 'medium', 'What does the customer service metric “FCR” stand for?', 'First Contact Resolution', 'Solving the issue on the first contact improves satisfaction and reduces workload.', 10, []],
        ['Customer Service', 'open_ended', 'medium', 'Give two examples of positive language you can use instead of “I can’t do that”.', 'e.g. “What I can do is…”, “Let me find the best option for you…”', 'Positive language keeps the focus on solutions.', 10, []],
        ['Customer Service', 'scenario', 'hard', 'A loyal customer asks you to share another client’s pricing. How do you respond?', 'Politely decline – client information is confidential – and focus on the value we can offer them.', 'Confidentiality protects all customers and the company.', 20, [], 'd'],
        // ---------------- Health and Safety
        ['Health and Safety', 'multiple_choice', 'easy', 'What does PPE stand for?', 'Personal Protective Equipment', 'PPE includes safety boots, high-visibility vests, gloves and helmets.', 10, ['*Personal Protective Equipment', 'Primary Production Equipment', 'Packing and Pallet Equipment', 'Personal Performance Evaluation']],
        ['Health and Safety', 'true_false', 'easy', 'Only trained and authorised staff may operate a forklift.', 'True', 'Forklift operation requires certification to prevent serious injuries.', 10, []],
        ['Health and Safety', 'multiple_choice', 'medium', 'What is the safest way to lift a heavy box from the floor?', 'Bend your knees, keep your back straight and the load close to your body', 'Lifting with the legs, not the back, prevents musculoskeletal injuries.', 10, ['Bend at the waist with straight legs', '*Bend the knees, straight back, load close', 'Twist quickly while lifting', 'Lift with one hand to stay balanced']],
        ['Health and Safety', 'short_answer', 'medium', 'What should you do immediately after a near-miss incident?', 'Report it so the cause can be investigated and fixed', 'Near-miss reporting prevents future accidents.', 10, []],
        ['Health and Safety', 'multiple_choice', 'hard', 'Which fire extinguisher type should NOT be used on an electrical fire?', 'Water', 'Water conducts electricity; use CO₂ or dry powder on electrical fires.', 15, ['CO₂', 'Dry powder', '*Water', 'Clean agent'], 'd'],
        ['Health and Safety', 'true_false', 'medium', 'Emergency exits can be temporarily blocked with pallets during busy periods.', 'False', 'Emergency routes must be kept clear at all times.', 10, []],
        ['Health and Safety', 'multiple_choice', 'easy', 'What colour are most emergency exit signs?', 'Green', 'Green signage indicates safe conditions and escape routes.', 10, ['Red', '*Green', 'Blue', 'Yellow']],
        // ---------------- Company Policies
        ['Company Policies', 'multiple_choice', 'easy', 'What is the TradeSmart Supplies Limited tagline?', 'Together Values', 'Our tagline reflects how we create value together with customers and colleagues.', 10, ['Smart Trade, Smart Life', '*Together Values', 'Supply Beyond Limits', 'Trade With Trust']],
        ['Company Policies', 'true_false', 'easy', 'Company passwords may be shared with trusted colleagues to save time.', 'False', 'Passwords are personal; sharing them breaks accountability and security.', 10, []],
        ['Company Policies', 'short_answer', 'medium', 'Who should you inform if you notice a conflict of interest in your work?', 'Your line manager (and HR/Compliance as required by policy)', 'Declaring conflicts early protects you and the company.', 10, []],
        ['Company Policies', 'open_ended', 'medium', 'Name one of the company values and give an example of living it at work.', 'Answers vary – e.g. teamwork: helping another shift clear a backlog.', 'Values become real through everyday behaviour.', 10, []],
        // ---------------- Compliance
        ['Compliance', 'multiple_choice', 'medium', 'Why must stock adjustments be approved and documented?', 'To keep an audit trail and prevent fraud or unexplained losses', 'Unapproved adjustments can hide theft or errors.', 10, ['To slow down the process', '*Audit trail and fraud prevention', 'Because the system requires a signature', 'To increase stock value']],
        ['Compliance', 'true_false', 'medium', 'Customer personal data should only be used for the purpose it was collected for.', 'True', 'This is a core principle of data protection law.', 10, []],
        ['Compliance', 'short_answer', 'hard', 'What is the name for separating duties so one person cannot both approve and pay a purchase?', 'Segregation of duties', 'Segregation of duties is a key internal control against fraud.', 15, [], 'b'],
        ['Compliance', 'scenario', 'hard', 'You discover an expired weighing scale calibration certificate in dispatch. What do you do?', 'Stop using / tag the scale, report it, and arrange recalibration before further use.', 'Uncalibrated equipment can cause short-weight deliveries and regulatory issues.', 20, [], 'd'],
        // ---------------- Operations
        ['Operations', 'multiple_choice', 'easy', 'What does KPI stand for?', 'Key Performance Indicator', 'KPIs measure progress against important goals.', 10, ['*Key Performance Indicator', 'Known Process Item', 'Key Purchase Invoice', 'Kilogram Per Item']],
        ['Operations', 'multiple_choice', 'medium', 'In the “5S” method, what does the first S – Sort – involve?', 'Removing items that are not needed from the work area', '5S: Sort, Set in order, Shine, Standardise, Sustain.', 10, ['Cleaning the floor', '*Removing unneeded items', 'Labelling shelves', 'Training staff']],
        ['Operations', 'short_answer', 'medium', 'Name the five steps of the 5S workplace method.', 'Sort, Set in order, Shine, Standardise, Sustain', '5S creates a safe, organised and efficient workplace.', 15, [], 'b'],
        ['Operations', 'multiple_choice', 'hard', 'What is a bottleneck in an operations process?', 'The step with the lowest capacity that limits total output', 'Improving anything other than the bottleneck rarely increases throughput.', 15, ['The fastest step', '*The step with lowest capacity that limits output', 'The final quality check', 'A broken bottle']],
        ['Operations', 'true_false', 'easy', 'Standard Operating Procedures (SOPs) help different people perform a task consistently.', 'True', 'SOPs reduce errors and make training easier.', 10, []],
        ['Operations', 'multiple_choice', 'medium', 'What does the PDCA cycle stand for?', 'Plan, Do, Check, Act', 'PDCA is a continuous-improvement loop.', 10, ['*Plan, Do, Check, Act', 'Prepare, Deliver, Collect, Audit', 'Price, Demand, Cost, Analysis', 'Plan, Document, Confirm, Approve']],
        ['Operations', 'scenario', 'medium', 'Two urgent orders are due at the same time but you only have one available vehicle. How do you decide?', 'Check customer priority/SLA, delivery locations and order size; consult your supervisor and communicate with both customers.', 'Use agreed priorities and communicate early.', 15, []],
        // ---------------- General Knowledge
        ['General Knowledge', 'multiple_choice', 'easy', 'How many sides does a standard pallet have?', 'Four', 'A rectangle has four sides – forklifts can enter from two or four sides depending on the pallet type.', 10, ['Three', '*Four', 'Six', 'Eight']],
        ['General Knowledge', 'multiple_choice', 'easy', 'Which is the largest ocean on Earth?', 'The Pacific Ocean', 'The Pacific covers about a third of Earth’s surface.', 10, ['Atlantic', 'Indian', '*Pacific', 'Arctic']],
        ['General Knowledge', 'true_false', 'easy', 'A kilogram of feathers weighs the same as a kilogram of steel.', 'True', 'A kilogram is a kilogram! Volume differs, weight does not.', 10, []],
        ['General Knowledge', 'multiple_choice', 'medium', 'What does the barcode standard “EAN” stand for?', 'European Article Number', 'EAN-13 barcodes are found on most retail products worldwide.', 10, ['Electronic Asset Number', '*European Article Number', 'Easy Access Number', 'Export Authorisation Number']],
        ['General Knowledge', 'short_answer', 'medium', 'How many hours are there in a standard week?', '168', '24 × 7 = 168 hours.', 10, []],
        // ---------------- Workshop Topics
        ['Workshop Topics', 'open_ended', 'medium', 'What is one key idea from today’s workshop that you will apply this week?', 'Facilitator to judge – any relevant, specific answer.', 'Turning learning into action is the goal of every workshop.', 10, []],
        ['Workshop Topics', 'open_ended', 'medium', 'Summarise today’s main topic in one sentence.', 'Facilitator to judge.', 'Clear summaries show understanding.', 10, []],
        ['Workshop Topics', 'mystery', 'medium', 'Mystery question: Which department would be most affected if Operations stopped for one day – and why?', 'Facilitator to judge – e.g. Sales/Customer Service due to missed deliveries.', 'Every department depends on Operations.', 15, []],
        // ---------------- Team Challenges
        ['Team Challenges', 'team', 'medium', 'Team challenge: As a team, list five ways to reduce waste in the warehouse in 30 seconds.', 'Any five valid ideas, e.g. reuse pallets, reduce over-packaging, recycle cardboard, prevent damage, reduce overstock.', 'Small waste reductions add up to big savings.', 20, []],
        ['Team Challenges', 'practical', 'easy', 'Practical challenge: Demonstrate the correct way to lift a box using a chair or bag as a prop.', 'Bend knees, straight back, load close, no twisting.', 'Safe lifting technique prevents injuries.', 15, []],
        ['Team Challenges', 'team', 'hard', 'Team challenge: Name all the documents in our order-to-delivery process, in order.', 'e.g. Customer order → Sales order → Picking list → Packing list → Delivery note/Invoice → POD', 'Knowing the document flow reduces errors and delays.', 25, [], 'b'],
        ['Team Challenges', 'practical', 'medium', 'Practical challenge: In 30 seconds, explain our delivery process to the person beside you as if they were a new customer.', 'Facilitator to judge clarity and accuracy.', 'Clear explanations build customer confidence.', 15, []],
        ['Team Challenges', 'bonus', 'medium', 'Bonus: Name three KPIs the Operations Department tracks.', 'e.g. OTIF, order accuracy, stock accuracy, picking rate, damages, lead time.', 'KPIs keep the team focused on what matters.', 20, [], 'b'],
    ];

    public function run(): void
    {
        $cats = QuestionCategory::pluck('id', 'name');
        foreach (self::QUESTIONS as $row) {
            [$cat, $type, $diff, $text, $answer, $explanation, $points, $options] = $row;
            $flags = $row[8] ?? '';
            $q = Question::updateOrCreate(['question_text' => $text], [
                'question_category_id' => $cats[$cat],
                'type' => $type,
                'difficulty' => $diff,
                'correct_answer' => $answer,
                'explanation' => $explanation,
                'points' => $points,
                'time_limit' => in_array($type, ['team', 'practical', 'scenario', 'open_ended'], true) ? 60 : null,
                'is_bonus' => str_contains($flags, 'b') || $type === 'bonus',
                'is_danger' => str_contains($flags, 'd'),
                'is_active' => true,
            ]);
            if ($type === 'true_false') {
                $options = [($answer === 'True' ? '*' : '').'True', ($answer === 'False' ? '*' : '').'False'];
            }
            $q->syncOptions(array_map(fn ($o) => ['text' => ltrim($o, '*'), 'is_correct' => str_starts_with($o, '*')], $options));
        }
    }
}

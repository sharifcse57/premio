<?php

namespace App\Http\Controllers;

use App\Models\Rule;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\AlertInformation;
use Illuminate\Support\Facades\Validator;

class RuleController extends Controller
{
    public function index()
    {
        $rules = Rule::where('user_id', auth()->id())->get();
        $alertInfo = AlertInformation::where('user_id', auth()->id())->first();
        $data = [];
        if($alertInfo){
            $data['client_id'] = $alertInfo->unique_id;
            $data['alertInfo'] = $alertInfo;
        }else{
            $data['client_id'] = '';
            $data['alertInfo'] = new AlertInformation();
        }
      
        $data['rules'] = $rules;
       
        return response()->json($data);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rules.*.show' => 'required|in:show,hide',
            'rules.*.type' => 'required',
            'rules.*.value' => 'required|string',
            'alertText' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rulesData = $request->input('rules');
        $alertText = $request->input('alertText');

        $alertInfo = AlertInformation::where('user_id', auth()->id())->first();
        if ($alertInfo) {
            $alertInfo->text = $alertText;
            $alertInfo->save();
        } else {
            $randomUniqueId = Str::uuid()->toString();
            $uniqueId = $randomUniqueId . '_' . auth()->id();
            AlertInformation::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'text' => $alertText,
                ],
                [
                    'user_id' => auth()->id(),
                    'text' => $alertText,
                    'unique_id' => $uniqueId
                ]
            );
        }

        Rule::where('user_id', auth()->id())->delete();

        // Insert each rule into the database
        foreach ($rulesData as $data) {
            $rule = new Rule();
            $rule->user_id = auth()->id();
            $rule->show = $data['show'];
            $rule->type = $data['type'];
            $rule->value = $data['value'];
            $rule->save();
        }

        return response()->json(['message' => 'Rules inserted successfully']);
    }

    public function generateJsSnippet(Request $request)
    {
        $string = $_SERVER['REQUEST_URI'];
        // Extract the number from the string using regex
        preg_match('/(\d+)$/', $string, $matches);
        if (isset($matches[1])) {
            $userId = (int)$matches[1];
        } else {
            $userId = null;
        }

        $rules = Rule::where('user_id', $userId)->get();
        $alertText = AlertInformation::where('user_id', $userId)->value('text');
        $alertText = $alertText ?? 'Hello world!';

        if ($rules) {
            foreach ($rules as $rule) {
                $condition = '';
                switch ($rule['type']) {
                    case 'contains':
                        $condition = "window.location.href.indexOf('{$rule['value']}') !== -1";
                        break;
                    case 'start_with':
                        $condition = "window.location.href.startsWith('{$rule['value']}')";
                        break;
                    case 'ends_with':
                        $condition = "window.location.href.endsWith('{$rule['value']}')";
                        break;
                    case 'specific_page':
                        $condition = "window.location.href === '{$rule['value']}'";
                        break;
                    case 'exact':
                        $condition = "window.location.href === '{$rule['value']}'";
                        break;
                    default:
                        $condition =  false;
                        break;
                }

                if ($rule['show'] === 'hide') {
                    $dontShowConditions[] = $condition;
                } else {
                    $showConditions[] = $condition;
                }
            }

            // Combine conditions for 'Show' and 'Don't Show' using logical OR (||)
            $showCondition = !empty($showConditions) ? '(' . implode(' || ', $showConditions) . ')' : 'false';
            $dontShowCondition = !empty($dontShowConditions) ? '(' . implode(' || ', $dontShowConditions) . ')' : 'false';

            // Construct the JavaScript snippet with the final combined condition
            $jsSnippet = "if ({$showCondition} && !({$dontShowCondition})) {";
            $jsSnippet .= "alert('$alertText!');";
            $jsSnippet .= "}";

            return response($jsSnippet)->header('Content-Type', 'text/javascript');
        }

        return response()->json(['success' => false]);
    }

    public function destroy($id)
    {
        Rule::where('user_id', auth()->id())->where('id', $id)->delete();
        return response()->json(['message' => 'Rule deleted successfully']);
    }
}
